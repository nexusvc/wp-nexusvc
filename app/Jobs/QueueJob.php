<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class QueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payload;

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    public function getTablePrefix($data = []) {
        if(array_key_exists('blog_id', $data['lead'])) {
            if($data['lead']['blog_id'] > 1) {
                return env('DB_PREFIX').$data['lead']['blog_id'].'_';
            } else {
                return env('DB_PREFIX');
            }
        }

        return env('DB_PREFIX');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {

            $original = $untransmit = unserialize(json_decode(base64_decode($this->payload)));

            // CLEAN . KEYS
            // STOP ZIPLOOKUP

            // Headers
            $headers = [
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($original['options']['username'].':'.$original['options']['password']),
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Content-MD5' => false,
                'Digest' => false
            ];

            $data = $original['lead'];

            // $client   = new \GuzzleHttp\Client;
            // $response = $client->get("https://api.zippopotam.us/us/{$data['zip']}");
            // $response = json_decode($response->getBody(true), true);

            // Formatters
            $data['dob'] = \Carbon\Carbon::parse($data['dob'])->format('m/d/Y');
            // $data['city'] = $response['places'][0]['place name'];
            // $data['state'] = $response['places'][0]['state'];

            $data['phone'] = normalize_phone_to_E164($data['phone']);
            if(array_key_exists('alt_phone', $data)) $data['alt_phone'] = normalize_phone_to_E164($data['alt_phone']);

            // Attach Form Meta
            // $data['source_meta'] = "base64:{$formMeta}";

            ksort($data);

            // dd($data);

            $payload = json_encode($data, JSON_UNESCAPED_SLASHES);

            $headers['Content-MD5'] = md5($payload);

            // Digest HASH
            // $digestHash = hash_hmac('SHA512', $payload, $original['options']['private_key'], TRUE);

            // Digest Header Value
            // $headers['Digest'] = 'hmac-sha512=' . base64_encode($digestHash);
            $mysqlConnection  = config('database.connections.mysql');
            $mysqlConnection['prefix'] = $this->getTablePrefix($original);
            \Config::set("database.connections.multisite", $mysqlConnection);

            $entry = \App\Models\GfEntry::on('multisite')->find($original['lead']['source_id']);
            $meta  = $entry->meta()->whereIn('meta_key', ['api_response', 'lead_id', 'api_status'])->get();

            $client = new \GuzzleHttp\Client;
            try {
                $request = $client->request('POST', $original['options']['api_url'], [
                  'form_params' => $data,
                  'headers' => $headers,
                ]);
                $response = json_decode($request->getBody(true),true);
                $subType  = $request->getStatusCode() == 200 ? 'success' : 'error';
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $response = json_decode($e->getResponse()->getBody(true), true);
                $subType  = 'error';
            }

            $note = new \App\Models\GfEntryNote;
            $note = $note->setConnection('multisite');
            $note->entry_id = $entry->id;
            $note->user_name = 'NXVC-API';
            $note->user_id = 0;
            $note->date_created = date('Y-m-d H:i:s');
            $note->value = json_encode($response);
            $note->note_type = "notification";
            $note->sub_type = $subType;
            $note->save();

            $meta->map(function($attr) use ($response) {
              if($attr->meta_key == 'api_response') {
                  // if(array_key_exists('errors', $response)) {
                  //     $attr->meta_value = '';
                  //     foreach($response['errors'] as $field => $errors) {
                  //         foreach($errors as $error) {
                  //             $field = Str::title($field);
                  //             $attr->meta_value .= "{$field}: {$error}\r\n";
                  //         }
                  //     }
                  // } else {
                      $attr->meta_value = "<pre>".json_encode($response, JSON_PRETTY_PRINT)."</pre>";
                  // }
              }

              if(!array_key_exists('status', $response)) {
                $response['status'] = 'error';
              }

              if($attr->meta_key == 'api_status') $attr->meta_value = Str::title($response['status']);

              if($response['status'] == 'success') {
                  if($attr->meta_key == 'lead_id') $attr->meta_value = $response['response']['uid'];
              }

              $attr->save();
            });

        } catch(\Exception $e) {

            $this->fail($e);

        }


    }
}
