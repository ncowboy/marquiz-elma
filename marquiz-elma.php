<?php

$data = json_decode(file_get_contents('php://input'), true);

$config = require_once 'marquiz-elma-config.php';

$quiz_name = $data['quiz']['name'];
$answers = $data['answers'];
$phone = $data['contacts']['phone'];
$email = $data['contacts']['email'];
$name = $data['contacts']['name'];
$company_name = $data['contacts']['text'];


if (!$data || !$quiz_name || !$answers || !$phone || !$email || !$name || !$company_name) {
    http_response_code(400);
    echo 'Bad request';
    exit;
}

$services = get_services($answers[0]['a'], $config['service_ids']);

$contact_response = send_request($config['elma_api_url'], '_contacts/create', $config['elma_api_token'], [
    'context' => [
        '_fullname' => [
            'lastname' => $name
        ],
        '_phone' => [
            [
                'type' => 'work',
                'tel' => $phone
            ]
        ],
        '_email' => [
            [
                'type' => 'work',
                'email' => $email
            ]
        ],
        'rol' => [
            [
                'code' => 'kontaktnoe_lico',
                'name' => 'Контактное лицо'
            ]
        ],
        'comment' => "Контакт из квиза {$quiz_name}"
    ]
]);

$contact_id = $contact_response->item->__id;

$lead_response = send_request($config['elma_api_url'], '_opportunities/create', $config['elma_api_token'], [
    'context' => [
        '__name' => $company_name,
        '_owner' => [$config['lead_owner_id']],
        '_contacts' => [$contact_id],
        '_lead_source' => [$config['lead_source_id']],
        'services' => $services,
        'lead_email' => [
            [
                'type' => 'work',
                'email' => $email
            ]
        ],
        'lead_phone' => [
            [
                'type' => 'work',
                'tel' => $phone
            ]
        ],
        'about_lead' => "Количество сотрудников: {$answers[1]['a']}\nТип сотрудничества: {$answers[2]['a']}"
    ]
]);

http_response_code(200);

exit();


/**
 * @param string $method
 * @param string $token ELMA API token
 * @param array $options
 * @return stdClass response
 */
function send_request(string $url, string $method, string $token, array $options): stdClass
{

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "{$url}/{$method}",
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($options)
    ]);

    $response = json_decode(curl_exec($curl));

    curl_close($curl);

    return $response;
}

/**
 * @param array $service_answer
 * @param array $service_mapping
 * @return array
 */
function get_services(array $service_answer, array $service_mapping): array
{
    $services = [];

    foreach ($service_answer as $service) {
        $services[] = $service_mapping[$service];
    }

    return $services;
}