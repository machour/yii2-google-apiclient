<?php

namespace machour\yii2\google\apiclient\commands;

use Google_Client;
use Yii;
use yii\console\Controller;
use yii\helpers\Json;

/**
 * This command interacts with Google API in order to set up your environment.
 *
 * @author Mehdi Achour <machour@gmail.com>
 * @since 1.0
 */
class GoogleController extends Controller
{

    /**
     * Google API discovery backend
     */
    const DISCOVERY_URL = 'https://www.googleapis.com/discovery/v1/apis';

    /**
     * @var array Api cache for the {getApis} getter
     */
    private $theApis = [];

    /**
     * @var string The access tokens directory
     */
    public $configPath = '@runtime/google-apiclient/';

    /**
     * @var string
     */
    public $clientSecretPath = 'gmail.json';


    /**
     * Configures a Google API
     *
     * This command echoes what you have entered as the message.
     * @param string $id The api identifier. Will be prompted for if not provided.
     */
    public function actionConfigure($id = '')
    {

        if (!$id || !isset($this->apis[$id])) {
            if ($id) {
                $this->stderr("Error: Unknown API requested: $id, prompting for the correct one..\n");
            }
            // prompt for the api to use
            $options = [];
            foreach ($this->apis as $id => $versions) {
                foreach ($versions as $version => $api) {
                    $options[$id] = $api->title;
                    break;
                }
            }
            $id = $this->select("Pick an API to connect to", $options);
        }

        $version = false;
        if (count($this->apis[$id]) > 1) {
            if ($this->prompt("The $id API has several versions. Install preferred version?")) {
                foreach ($this->apis[$id] as $api) {
                    if ($api->preferred) {
                        $version = $api->version;
                    }
                }
            } else {
                $versions = [];
                foreach ($this->apis[$id] as $version => $api) {
                    $versions[$version] = $version;
                }
                $version = $this->select("Pick the desired version number", $versions);
            }
        } else {
            $version = array_keys($this->apis[$id])[0];
        }

        if ($version) {
            // Discover the API
            $api = $this->apis[$id][$version];

            $response = Json::decode(file_get_contents($api->discoveryRestUrl), false);

            $scopes = [];
            // Prompt for scopes if any
            if (isset($response->auth->oauth2->scopes)) {
                $this->stdout("Available scopes :\n");
                $availableScopes = [];
                foreach ($response->auth->oauth2->scopes as $scope => $desc) {
                    $availableScopes[] = $scope;
                    $this->stdout("  $scope\t\t{$desc->description}\n");
                }

                $done = false;
                while (!$done) {
                    $inputs = explode(',', $this->prompt("Please enter the required scopes separated by a comma:"));
                    $scopes = [];

                    foreach ($inputs as $input) {
                        $input = trim($input);
                        if ($input) {
                            if (!in_array($input, $availableScopes)) {
                                $this->stderr("Error in the input string, prompting again...\n\n");
                                continue 2;
                            } else {
                                $scopes[] = $input;
                            }
                        }
                    }

                    $done = true;
                }
            }

            $credentialsPath = $this->generateCredentialsFile($id, $scopes);
            $this->stdout(sptrinf("Credentials saved to %s\n\n", $credentialsPath));



        } else {
            $this->stderr("Something went terribly wrong..\n");
        }
    }

    /**
     * List all the available APIs
     *
     * @param bool|false $showAllVersions Whether to show all versions of each API
     */
    public function actionIndex($showAllVersions = false)
    {
        foreach ($this->apis as $id => $versions) {
            foreach ($versions as $version => $api) {
                $this->stdout($api->title . "\n");
                if (!$showAllVersions) {
                    break;
                }
            }
        }
    }

    /**
     * Fetches the Google API list
     *
     * @return array
     */
    public function getApis()
    {
        if (empty($this->theApis)) {
            $response = Json::decode(file_get_contents(self::DISCOVERY_URL), false);

            $theApis = [];
            foreach ($response->items as $item) {
                if (!isset($theApis[$item->name])) {
                    $theApis[$item->name] = [];
                }
                $theApis[$item->name][$item->version] = $item;
            }
            ksort($theApis);

            $this->theApis = $theApis;
        }

        return $this->theApis;
    }

    /**
     * Returns an authorized API client.
     *
     * @
     * @return Google_Client the authorized client object
     */
    private function generateCredentialsFile($api, $scopes) {

        $credentialsPath = Yii::getAlias($this->configPath) . '/' . $api . '_' . $this->getUuid() . '.json';

        $client = new Google_Client();
        $client->setAuthConfigFile(Yii::getAlias($this->configPath) . '/' . $this->clientSecretPath);
        $client->setAccessType('offline');
        $client->setScopes(implode(' ', $scopes));

        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        $this->stdout(sprintf("Open the following link in your browser:\n  %s\n", $authUrl));

        $authCode = $this->prompt("Enter the verification code: ");

        // Exchange authorization code for an access token.
        $accessToken = $client->authenticate($authCode);

        // Store the credentials to disk.
        if(!file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, $accessToken);

        return $credentialsPath;

    }

    /**
     * Generates a v4 UUID
     *
     * @return string
     */
    private function getUuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }


    /**
     * Expands the home directory alias '~' to the full path.
     * @param string $path the path to expand.
     * @return string the expanded path.
     */
    private function expandHomeDirectory($path) {
        $homeDirectory = getenv('HOME');
        if (empty($homeDirectory)) {
            $homeDirectory = getenv("HOMEDRIVE") . getenv("HOMEPATH");
        }
        return str_replace('~', realpath($homeDirectory), $path);
    }
}
