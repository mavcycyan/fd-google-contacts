<?php

namespace rapidweb\googlecontacts\factories;

use rapidweb\googlecontacts\helpers\GoogleHelper;
use rapidweb\googlecontacts\objects\Contact;

abstract class ContactFactory
{
    public static function getAll($customConfig = NULL)
    {
        $client = GoogleHelper::getClient($customConfig);

        $req = new \Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full?max-results=10000&updated-min=2007-03-16T00:00:00');

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();

        $xmlContacts = simplexml_load_string($response);
        $xmlContacts->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $contactsArray = array();

        foreach ($xmlContacts->entry as $xmlContactsEntry) {
            $contactDetails = array();

            $contactDetails['id'] = (string) $xmlContactsEntry->id;
            $contactDetails['name'] = (string) $xmlContactsEntry->title;
            $contactDetails['content'] = (string) $xmlContactsEntry->content;

            foreach ($xmlContactsEntry->children() as $key => $value) {
                $attributes = $value->attributes();

                if ($key == 'link') {
                    if ($attributes['rel'] == 'edit') {
                        $contactDetails['editURL'] = (string) $attributes['href'];
                    } elseif ($attributes['rel'] == 'self') {
                        $contactDetails['selfURL'] = (string) $attributes['href'];
                    } elseif ($attributes['rel'] == 'http://schemas.google.com/contacts/2008/rel#edit-photo') {
                        $contactDetails['photoURL'] = (string) $attributes['href'];
                    }
                }
            }

            $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');
            foreach ($contactGDNodes as $key => $value) {
                switch ($key) {
                    case 'organization':
                        $contactDetails[$key]['orgName'] = (string) $value->orgName;
                        $contactDetails[$key]['orgTitle'] = (string) $value->orgTitle;
                        break;
                    case 'email':
                        $attributes = $value->attributes();
                        $emailadress = (string) $attributes['address'];
                        $emailtype = substr(strstr($attributes['rel'], '#'), 1);
                        $contactDetails[$key][] = ['type' => $emailtype, 'email' => $emailadress];
                        break;
                    case 'phoneNumber':
                        $attributes = $value->attributes();
                        //$uri = (string) $attributes['uri'];
                        $type = substr(strstr($attributes['rel'], '#'), 1);
                        //$e164 = substr(strstr($uri, ':'), 1);
                        $contactDetails[$key][] = ['type' => $type, 'number' => $value->__toString()];
                        break;
                    default:
                        $contactDetails[$key] = (string) $value;
                        break;
                }
            }

            $contactsArray[] = new Contact($contactDetails);
        }

        return $contactsArray;
    }

    public static function getBySelfURL($selfURL, $customConfig = NULL)
    {
        $client = GoogleHelper::getClient($customConfig);

        $req = new \Google_Http_Request($selfURL);

        try {
            $val = $client->getAuth()->authenticatedRequest($req);
        } catch(Exception $e) {
            return;
        }

        $response = $val->getResponseBody();

        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;
        $contactDetails['content'] = (string) $xmlContactsEntry->content;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'http://schemas.google.com/contacts/2008/rel#edit-photo') {
                    $contactDetails['photoURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');
        foreach ($contactGDNodes as $key => $value) {
            switch ($key) {
                case 'organization':
                    $contactDetails[$key]['orgName'] = (string) $value->orgName;
                    $contactDetails[$key]['orgTitle'] = (string) $value->orgTitle;
                    break;
                case 'email':
                    $attributes = $value->attributes();
                    $emailadress = (string) $attributes['address'];
                    $emailtype = substr(strstr($attributes['rel'], '#'), 1);
                    $contactDetails[$key][] = ['type' => $emailtype, 'email' => $emailadress];
                    break;
                case 'phoneNumber':
                    $attributes = $value->attributes();
                    $uri = (string) $attributes['uri'];
                    $type = substr(strstr($attributes['rel'], '#'), 1);
                    $e164 = substr(strstr($uri, ':'), 1);
                    $contactDetails[$key][] = ['type' => $type, 'number' => $e164];
                    break;
                default:
                    $contactDetails[$key] = (string) $value;
                    break;
            }
        }

        return new Contact($contactDetails);
    }

    public static function submitUpdates(Contact $updatedContact, $customConfig = NULL)
    {
        $client = GoogleHelper::getClient($customConfig);

        $req = new \Google_Http_Request($updatedContact->selfURL);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();

        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $xmlContactsEntry->title = $updatedContact->name;

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');

        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'email') {
                $attributes['address'] = $updatedContact->email;
            } else {
                $xmlContactsEntry->$key = $updatedContact->$key;
                $attributes['uri'] = '';
            }
        }

        $updatedXML = $xmlContactsEntry->asXML();

        $req = new \Google_Http_Request($updatedContact->editURL);
        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed; ', 'Gdata-version' => '3.0'));
        $req->setRequestMethod('PUT');
        $req->setRequestHeaders(array('If-Match' => '*'));
        //$req->setPostBody($updatedXML);

        /*rewrite XML generating ($updatedXML ignoring)*/
        list($givenName, $familyName) = explode(" ", $updatedContact->name);

        $fullName = $givenName.' '.$familyName;

        $birthday = strtotime($updatedContact->birthday);
        $birthday = date('Y-m-d', $birthday);
        
        $xmlCode = '<?xml version="1.0" encoding="UTF-8" ?>';
        $xmlCode .= '<entry xmlns="http://www.w3.org/2005/Atom" xmlns:batch="http://schemas.google.com/gdata/batch" xmlns:gd="http://schemas.google.com/g/2005" xmlns:gContact="http://schemas.google.com/contact/2008">';
        $xmlCode .= '<category scheme="http://schemas.google.com/g/2005#kind" term="http://schemas.google.com/contact/2008#contact"/>';
        $xmlCode .= '<gd:name>';
        $xmlCode .= '<gd:givenName>'.$givenName.'</gd:givenName>';
        $xmlCode .= '<gd:familyName>'.$familyName.'</gd:familyName>';
        $xmlCode .= '<gd:fullName>'.$fullName.'</gd:fullName>';
        $xmlCode .= '</gd:name>';
        $xmlCode .= '<title type="text">'.$fullName.'</title>';
        $xmlCode .= '<content type="text">'.$updatedContact->content.'</content>';
        $xmlCode .= '<link rel="self" type="application/atom+xml" href="'.$updatedContact->selfURL.'"/>';
        $xmlCode .= '<link rel="edit" type="application/atom+xml" href="'.$updatedContact->editURL.'"/>';
        $xmlCode .= '<gd:email rel="http://schemas.google.com/g/2005#work" address="'.$updatedContact->email.'"/>';
        $xmlCode .= '<gd:phoneNumber rel="http://schemas.google.com/g/2005#work">'.$updatedContact->phoneNumber.'</gd:phoneNumber>';
        $xmlCode .= '<gd:phoneNumber rel="http://schemas.google.com/g/2005#whatsapp">'.$updatedContact->whatsapp.'</gd:phoneNumber>';
        $xmlCode .= '<gContact:birthday rel="Contacts" href="http://www.google.com/m8/feeds/groups/'.$updatedContact->adminMail.'/base/6"/>';

        if($birthday && $birthday != '' && $birthday != null) {
            $xmlCode .= '<gContact:birthday when="'.$birthday.'"/>';
        }
        $xmlCode .= '<gContact:groupMembershipInfo rel="Contacts" href="http://www.google.com/m8/feeds/groups/'.$updatedContact->adminMail.'/base/6"/>';
        $xmlCode .= '</entry>';

        $req->setPostBody($xmlCode);

        /*rewrite XML generating ($updatedXML ignoring) END*/

        $val = $client->getAuth()->authenticatedRequest($req);
        $response = $val->getResponseBody();

//        $xmlContact = simplexml_load_string($response);
//        $xmlContact->registerXPathNamespace('gContact', 'http://schemas.google.com/contact/2008');
//        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');
//
//        $xmlContactsEntry = $xmlContact;
//
//        $contactDetails = array();
//
//        $contactDetails['id'] = (string) $xmlContactsEntry->id;
//        $contactDetails['name'] = (string) $xmlContactsEntry->title;
//        $contactDetails['content'] = (string) $xmlContactsEntry->content;
//
//        foreach ($xmlContactsEntry->children() as $key => $value) {
//            $attributes = $value->attributes();
//
//            if ($key == 'link') {
//                if ($attributes['rel'] == 'edit') {
//                    $contactDetails['editURL'] = (string) $attributes['href'];
//                } elseif ($attributes['rel'] == 'self') {
//                    $contactDetails['selfURL'] = (string) $attributes['href'];
//                }
//            }
//        }
//
//        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');
//
//        foreach ($contactGDNodes as $key => $value) {
//            $attributes = $value->attributes();
//
//            if ($key == 'email') {
//                $contactDetails[$key] = (string) $attributes['address'];
//            } else {
//                $contactDetails[$key] = (string) $value;
//            }
//        }
//
//        return new Contact($contactDetails);
    }

    public static function create($name, $phoneNumber, $emailAddress, $note, $whatsapp = '', $birthday, $adminMail, $customConfig = NULL)
    {
        $doc = new \DOMDocument();
        $doc->formatOutput = true;
        $entry = $doc->createElement('atom:entry');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gContact', 'http://schemas.google.com/contact/2008');
        $doc->appendChild($entry);

        $categ = $doc->createElement('atom:category');
        $categ->setAttribute('scheme', 'http://schemas.google.com/g/2005#kind');
        $categ->setAttribute('term', 'http://schemas.google.com/contact/2008#contact');
        $entry->appendChild($categ);

        $title = $doc->createElement('title', $name);
        $entry->appendChild($title);

        $email = $doc->createElement('gd:email');
        $email->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
        $email->setAttribute('address', $emailAddress);
        $entry->appendChild($email);

        $contact = $doc->createElement('gd:phoneNumber', $phoneNumber);
        $contact->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
        $entry->appendChild($contact);

        /*Correct fullname saving*/
        list($givenName, $familyName) = explode(" ", $name);

        $fullName = $givenName.' '.$familyName;

        $cName = $doc->createElement('gd:name');
        $givenName = $doc->createElement('gd:givenName', $givenName);
        $familyName = $doc->createElement('gd:familyName', $familyName);
        $fullName = $doc->createElement('gd:fullName', $fullName);
        $cName->appendChild($givenName);
        $cName->appendChild($familyName);
        $cName->appendChild($fullName);
        $entry->appendChild($cName);

        if($whatsapp || $whatsapp != '') {
            $wa = $doc->createElement('gd:phoneNumber', $whatsapp);
            $wa->setAttribute('rel', 'http://schemas.google.com/g/2005#whatsapp');
            $entry->appendChild($wa);
        }
        /*Correct fullname saving*/

        $note = $doc->createElement('atom:content', $note);
        $note->setAttribute('rel', 'http://schemas.google.com/g/2005#kind');
        $entry->appendChild($note);

        if($birthday && ($birthday != '') && ($birthday != null)) {
            $birthday = strtotime($birthday);
            $birthday = date('Y-m-d', $birthday);;

            $birthDay = $doc->createElement('gContact:birthday');
            $birthDay->setAttribute('when', $birthday);
            $entry->appendChild($birthDay);
        }

        $sysGr = $doc->createElement('gContact:groupMembershipInfo');
        $sysGr->setAttribute('rel', 'Contacts');
        $sysGr->setAttribute('href', 'http://www.google.com/m8/feeds/groups/'.$adminMail.'/base/6');
        $entry->appendChild($sysGr);

        $xmlToSend = $doc->saveXML();

        $client = GoogleHelper::getClient($customConfig);

        $req = new \Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full');
        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed; ', 'Gdata-version' => '3.0'));
        $req->setRequestMethod('POST');
        $req->setPostBody($xmlToSend);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();

        return;

        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');

        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'email') {
                $contactDetails[$key] = (string) $attributes['address'];
            } else {
                $contactDetails[$key] = (string) $value;
            }
        }

        return new Contact($contactDetails);
    }
    
    public static function delete(Contact $toDelete, $customConfig = NULL)
    {
        $client = GoogleHelper::getClient($customConfig);

        $req = new \Google_Http_Request($toDelete->editURL);
        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
        $req->setRequestMethod('DELETE');

        $client->getAuth()->authenticatedRequest($req);
    }
    
    public static function getPhoto($photoURL, $customConfig = NULL)
    {
        $client = GoogleHelper::getClient($customConfig);
        $req = new \Google_Http_Request($photoURL);
        $req->setRequestMethod('GET');
        $val = $client->getAuth()->authenticatedRequest($req);
        $response = $val->getResponseBody();
        return $response;
    }

    public static function updatePhoto($selfURL, $newImgURL, $customConfig = NULL)
    {
        $client = GoogleHelper::getClient($customConfig);
        $req = new \Google_Http_Request($selfURL);
        $req->setRequestMethod('PUT');

        $filename = $newImgURL;
        $file = file_get_contents($filename);
        //$contents = fread($file, filesize($filename));
        //fclose($file);
        $req->setPostBody($file);

        $val = $client->getAuth()->authenticatedRequest($req);
        $response = $val->getResponseBody();
        return $response;
    }
    public static function delPhoto($photoUrl, $photoEtag, $customConfig = NULL)
    {

        $client = GoogleHelper::getClient($customConfig);
        $req = new \Google_Http_Request($photoUrl);
        $req->setRequestMethod('DELETE');
        $req->setRequestHeaders(array('If-Match' => $photoEtag));

        $val = $client->getAuth()->authenticatedRequest($req);
        $response = $val->getResponseBody();
        return $response;
    }
}
