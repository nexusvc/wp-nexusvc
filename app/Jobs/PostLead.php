<?php

namespace App\Jobs;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

class PostLead {

    use InteractsWithQueue;

    protected $options;
    protected $entry;
    protected $post;
    protected $headers;

    public function __construct($entry, $options) {
        $this->options = $options;
        $this->entry = $entry;

        // Payload to Post
        $data = [
            'medium' => $this->options['medium']
        ];

        // Prepare the FileLoader
        $loader = new FileLoader(new Filesystem(), __DIR__.'/lang');

        // Register the English translator
        $map = new Translator($loader, "en");

        foreach($entry as $key => $value) {
            // Field Labels
            if (is_numeric($key)) $key = Str::snake(getFormLabel($entry['form_id'], $key));

            // Rewrite Labels
            if($key == 'id') $key = 'source_id';
            if($key == 'form_id') $key = 'source_form';

            // Attach to Payload
            if(!in_array($key, excludedKeys())) $data[$key] = $value;
        }

        $trans = [];
        foreach($data as $key => $value) {
            if($key != '') $trans[$map->get(strtolower($this->options['medium']).'.'.$key)] = $value;
        }

        // Use translated data
        $this->post = $trans;
        
        gform_update_meta( $entry['id'], 'api_response', 'Queued' );
    }

    public function handle() {
        
        $entry = $this->entry;

        // Headers
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($this->options['username'].':'.$this->options['password']),
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Content-MD5' => false,
            'Digest' => false
        ];

        $data = $this->post;

        $client   = new \GuzzleHttp\Client;
        $response = $client->get("https://api.zippopotam.us/us/{$data['zip']}");
        $response = json_decode($response->getBody(true), true);

        // Formatters
        $data['dob'] = \Carbon\Carbon::parse($data['dob'])->format('m/d/Y');
        $data['city'] = $response['places'][0]['place name'];
        $data['state'] = $response['places'][0]['state'];
        $data['campaign'] .= '.'.$response['places'][0]['state abbreviation'];
        $data['phone'] = normalize_phone_to_E164($data['phone']);

        // Attach Form Meta
        // $data['source_meta'] = "base64:{$formMeta}";

        ksort($data);

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);

        $headers['Content-MD5'] = md5($payload);

        // Digest HASH
        $digestHash = hash_hmac('SHA512', $payload, $this->options['private_key'], TRUE);

        // Digest Header Value
        $headers['Digest'] = 'hmac-sha512=' . base64_encode($digestHash);

        $entry = \App\Models\GfEntry::find($this->entry['id']);
        $meta  = $entry->meta()->whereIn('meta_key', ['api_response', 'lead_id', 'api_status'])->get();

        $client = new \GuzzleHttp\Client;
        try {
            $request = $client->request('POST', 'https://signal.leadtrust.io/api/post', [
              'form_params' => $data,
              'headers' => $headers,
            ]);
            $response = json_decode($request->getBody(true),true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = json_decode($e->getResponse()->getBody(true), true);
        }

        $meta->map(function($attr) use ($response) {
            if($attr->meta_key == 'api_response') $attr->meta_value = json_encode($response);
            if($attr->meta_key == 'api_status') $attr->meta_value = Str::title($response['status']);
            if($response['status'] == 'success') {
                if($attr->meta_key == 'lead_id') $attr->meta_value = $response['response']['uid'];
            }
            $attr->save();
        });
    }

}
