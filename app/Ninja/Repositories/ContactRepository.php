<?php namespace App\Ninja\Repositories;

use App\Models\Client;
use App\Models\Contact;

class ContactRepository extends BaseRepository
{
    public function save($data)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if (!$publicId || $publicId == '-1') {
            $contact = Contact::createNew();
            $contact->send_invoice = true;
            $contact->client_id = $data['client_id'];
            $contact->is_primary = Contact::scope()->where('client_id', '=', $contact->client_id)->count() == 0;
        } else {
            $contact = Contact::scope($publicId)->firstOrFail();
        }

        $contact->fill($data);
        $contact->save();

        return $contact;
    }
}