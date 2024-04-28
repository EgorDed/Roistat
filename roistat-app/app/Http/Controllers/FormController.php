<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CustomFields\CustomFieldsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\CustomFields\TextCustomFieldModel;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use Illuminate\Http\Request;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;

class FormController extends Controller
{
    public function handleForm(Request $request): void
    {
        $formFields = $request->validate([
            'name' => 'required',
            'phone' => 'required|max:12',
            'email' => 'required',
            'price' => 'required',
            'time' => 'required',
        ]);

        $apiClient = $this->amoLogin();
        $leadModel = $this->createLead($apiClient, $formFields);
        $contactModel = $this->createContact($apiClient, $formFields, $leadModel);
    }

    private function amoLogin(): AmoCRMApiClient
    {
        $apiClient = new AmoCRMApiClient(env('CLIENT_ID'), env('CLIENT_SECRET'), env('REDIRECT_URL'));
        $apiClient->setAccountBaseDomain(env('BASE_DOMAIN'));

        //авторизация
        if (file_get_contents('./token.json')) {
            $newToken = json_decode(file_get_contents('./token.json'), true);
            $accessToken = new AccessToken($newToken);

            $apiClient->setAccessToken($accessToken)
                ->setAccountBaseDomain(env('BASE_DOMAIN'))
                ->onAccessTokenRefresh(
                    function (AccessTokenInterface $accessToken) {
                        file_put_contents('./token.json', json_encode($accessToken->jsonSerialize(), JSON_PRETTY_PRINT));
                    }
                );
        } else {
            $accessToken = $apiClient->getOAuthClient()->getAccessTokenByCode(env('CODE'));

            file_put_contents('./token.json', json_encode($accessToken,JSON_PRETTY_PRINT));
        }

        return $apiClient;
    }

    private function createLead(AmoCRMApiClient $apiClient, array $formFields): LeadModel
    {
        $leadModel = new LeadModel();
        $leadsService = $apiClient->leads();

        $leadModel->setName('Сделка ')
            ->setPrice((int)$formFields['price']);

        $leadsService->addOne($leadModel);

        return $leadModel;
    }

    private function createContact(AmoCRMApiClient $apiClient, array $formFields, $leadModel): ContactModel {
        $contact = new ContactModel();

        $customFields = $contact->getCustomFieldsValues();

        $filteredFields = array_map(function ($item) {
            return strtoupper(trim($item));
        }, array_filter(array_keys($formFields), function ($item) {
            return $item !== 'name' && $item !== 'price';
        }));
        $contactModel = $this->fieldCheck($apiClient, $filteredFields, $formFields);

        $contactModel
            ->setName($formFields['name'])
            ->setLeads(new LeadsCollection($leadModel));

        $contactModel = $apiClient->contacts()->addOne($contactModel);

        $links = new LinksCollection();
        $links->add($leadModel);
        $apiClient->contacts()->link($contactModel, $links);

        return $contactModel;
    }

    private function fieldCheck(AmoCRMApiClient $apiClient, array $fieldsCodes, array $values): ContactModel
    {
        $customFieldsService = $apiClient->customFields(EntityTypesInterface::CONTACTS);
        $customFieldsCollection = new CustomFieldsCollection();
        $collection = new CustomFieldsValuesCollection();

        $fields = [];
        foreach ($customFieldsService->get()->toArray() as $key => $field) {
            if ($field['code'] === 'TIME') {
                $fields[] = $field;
            }
        }
        if (empty($fields)) {
            $cf = new TextCustomFieldModel();
            $cf ->setName('Time')
                ->setCode('TIME');

            $customFieldsCollection->add($cf);

            $customFieldsService->add($customFieldsCollection);
        }

        $contact = (new ContactModel())->setCustomFieldsValues($collection);

        foreach ($fieldsCodes as $key => $codeName) {
            $field = $contact->getCustomFieldsValues()->getBy('fieldCode', $codeName);

            if (empty($field)) {
                $field = (new TextCustomFieldValuesModel())
                    ->setFieldCode($codeName)
                    ->setFieldName(strtolower($codeName));
                $contact->getCustomFieldsValues()->add($field);
            }
            //Установим значение поля
            $field->setValues(
                (new TextCustomFieldValueCollection())
                    ->add(
                        (new TextCustomFieldValueModel())
                            ->setValue($values[strtolower($codeName)])
                    )
            );
        }

        return $contact;
    }
}
