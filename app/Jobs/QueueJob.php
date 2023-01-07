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
        

            if(array_key_exists('blog_id', $original['lead'])) {
                if($original['lead']['blog_id'] > 1) {
                    $entry = \DB::table($original['lead']['blog_id'].'_gf_entry');
                } else {
                    $entry = \DB::table('gf_entry');
                }
            }
            $entry = $entry->where('id', $original['lead']['source_id'])->first();
            
            // $fail = new \Exception('Failed: '.json_encode($entry));
            // return $this->fail($fail);

            if(array_key_exists('blog_id', $original['lead'])) {
                if($original['lead']['blog_id'] > 1) {
                    $meta = \DB::table($original['lead']['blog_id'].'_gf_entry_meta');
                } else {
                    $meta = \DB::table('gf_entry_meta');
                }
            }

            $meta  = $meta->where('entry_id', $original['lead']['source_id'])->whereIn('meta_key', ['api_response', 'lead_id', 'api_status'])->get();

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

            // $note = new \App\Models\GfEntryNote;

            if(array_key_exists('blog_id', $original['lead'])) {
                if($original['lead']['blog_id'] > 1) {
                    $noteTable = env('DB_PREFIX').$original['lead']['blog_id'].'_gf_entry_notes';
                } else {
                    $noteTable = env('DB_PREFIX').'gf_entry_notes';
                }
            }

            \DB::insert("insert into {$noteTable} (entry_id, user_name, user_id, date_created, value, note_type, sub_type) values (?,?,?,?,?,?,?)", [
                $entry->id,
                'NXVC-API',
                0,
                date('Y-m-d H:i:s'),
                json_encode($response),
                'notification',
                $subType,
            ]);

            // $note->entry_id = $entry->id;
            // $note->user_name = 'NXVC-API';
            // $note->user_id = 0;
            // $note->date_created = date('Y-m-d H:i:s');
            // $note->value = json_encode($response);
            // $note->note_type = "notification";
            // $note->sub_type = $subType;
            // $note->save();

            if(array_key_exists('blog_id', $original['lead'])) {
                if($original['lead']['blog_id'] > 1) {
                    $metaTable = env('DB_PREFIX').$original['lead']['blog_id'].'_gf_entry_meta';
                } else {
                    $metaTable = env('DB_PREFIX').'gf_entry_meta';
                }
            }

            $meta->map(function($attr) use ($response, $metaTable) {
              if($attr->meta_key == 'api_response') {
                    $attr->meta_value = "<pre>".json_encode($response, JSON_PRETTY_PRINT)."</pre>";
                    \DB::update("update {$metaTable} set meta_value = ? WHERE id = ?", [
                        $attr->meta_value,
                        $attr->id
                    ]);
              }

              if(!array_key_exists('status', $response)) {
                $response['status'] = 'error';
              }

              if($attr->meta_key == 'api_status') {
                $attr->meta_value = Str::title($response['status']);
                \DB::update("update {$metaTable} set meta_value = ? WHERE id = ?", [
                    $attr->meta_value,
                    $attr->id
                ]);
              }

              if($response['status'] == 'success') {
                  if($attr->meta_key == 'lead_id') {
                    $attr->meta_value = $response['response']['uid'];
                    \DB::update("update {$metaTable} set meta_value = ? WHERE id = ?", [
                        $attr->meta_value,
                        $attr->id
                    ]);
                  }
              }

              // $attr->save();
            });

        } catch(\Exception $e) {

            $this->fail($e);

        }


    }
}
