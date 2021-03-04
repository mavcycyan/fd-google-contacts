<?php

require_once 'vendor/autoload.php';

class GoogleContacts
{
    public function getContact($email)
    {
        $contacts = $this->showList();

        if (!empty($contacts)) {
            foreach ($contacts as $contact) {
                if (isset($contact->email)) {
                    $contact_emails = $contact->email;
                    foreach ($contact_emails as $contact_email) {
                        if ($contact_email['email'] == $email) {
                            return $contact;
                        }
                    }
                }

            }
        }

        return false;
    }

    public function showList()
    {
        $contacts = rapidweb\googlecontacts\factories\ContactFactory::getAll();

        return $contacts;
    }

    public function createContact($name, $phoneNumber, $emailAddress, $note, $whatsapp = false, $birthday = false, $adminMail)
    {

        $newContact = rapidweb\googlecontacts\factories\ContactFactory::create($name, $phoneNumber, $emailAddress, $note, $whatsapp, $birthday, $adminMail);

        return $newContact;
    }

    public function deleteContact($email)
    {

        $contactUrl = $this->getContact($email);
        if($contactUrl != '' && $contactUrl != null) {
            $contact = rapidweb\googlecontacts\factories\ContactFactory::getBySelfURL($contactUrl->selfURL);

            $delContact = rapidweb\googlecontacts\factories\ContactFactory::delete($contact);

            return $delContact;
        }
    }

    public function updateContact($selfUrl, $name, $phoneNumber, $emailAddress, $note, $whatsapp = false, $birthday = false, $adminMail)
    {

        $contact = rapidweb\googlecontacts\factories\ContactFactory::getBySelfURL($selfUrl);

        $contact->title = $name;
        $contact->name = $name;
        $contact->phoneNumber = $phoneNumber;

        if ($whatsapp) {
            $contact->whatsapp = $whatsapp;
        }

        if ($birthday) {
            $contact->birthday = $birthday;
        }

        $contact->email = $emailAddress;
        $contact->content = $note;
        $contact->adminMail = $adminMail;

        $contactAfterUpdate = rapidweb\googlecontacts\factories\ContactFactory::submitUpdates($contact);

        return $contactAfterUpdate;
    }

    public function updatePhoto($contEmail, $newImgURL)
    {

        $contact = $this->getContact($contEmail);

        $photoUrl = str_replace('contacts', 'photos/media', $contact->selfURL);
        $photoUrl = str_replace('/full', '', $photoUrl);

        $photo = rapidweb\googlecontacts\factories\ContactFactory::updatePhoto($photoUrl, $newImgURL);

        return $photo;
    }

    public function delPhoto($contEmail)
    {

        $contact = $this->getContact($contEmail);

        $photoUrl = str_replace('contacts', 'photos/media', $contact->selfURL);
        $photoUrl = str_replace('/full', '', $photoUrl);
        $photoEtag = str_replace($photoUrl . '/', '', $contact->photoURL);

        $photo = rapidweb\googlecontacts\factories\ContactFactory::delPhoto($photoUrl, $photoEtag);

        return $photo;
    }
}

