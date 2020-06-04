<?php
/**
 * Copyright 2018 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
// [START people_quickstart]
require __DIR__ . '/vendor/autoload.php';

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Google Fit API PHP Quickstart');
    $client->setScopes(Google_Service_Fitness::FITNESS_ACTIVITY_READ);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}


// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Fitness($client);

/* get steps from yesterday */
$time_offset = 84600;
$time_start = mktime(0, 0, 0) - $time_offset;
$time_finish = mktime(23, 59, 59) - $time_offset;

$datasets = $service->users_dataset;

$aggBy = new Google_Service_Fitness_AggregateBy();
$aggBy->setDataTypeName("com.google.step_count.delta");

$bucketBy = new Google_Service_Fitness_BucketByTime();
$bucketBy->setDurationMillis("86400000");

$aggReq = new Google_Service_Fitness_AggregateRequest();
$aggReq->startTimeMillis = $time_start * 1000;
$aggReq->endTimeMillis = $time_finish * 1000;

$aggReq->setAggregateBy([$aggBy]);
$aggReq->setBucketByTime($bucketBy);

$aggregates = $datasets->aggregate('me', $aggReq);

$steps = -1;

foreach ($aggregates->getBucket() as $bucket) {
    $datasets = $bucket->getDataset();

    foreach ($datasets as $d) {
        $points = $d->getPoint();

        foreach ($points as $dp) {
            $values = $dp->getValue();

            foreach ($values as $value) {
                $steps = $value->getIntVal();
            }
        }
    }
}

print sprintf("Steps: %d\n", $steps);

