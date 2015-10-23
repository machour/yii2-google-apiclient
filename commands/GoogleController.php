<?php

namespace machour\yii2\google\apiclient\commands;

use Google_Auth_Exception;
use Google_Client;
use Yii;
use yii\console\Controller;
use yii\console\Exception;
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
    public $clientSecretPath = '@runtime/google-apiclient/secret.json';


    /**
     * Prints this help message
     */
    public function actionIndex()
    {
        $this->run('/help', ['google_apiclient']);
    }

    /**
     * Configures a Google API
     *
     * @param string $clientSecretPath The client secret file path
     * @param string $api The api identifier. Will be prompted for if not provided.
     * @throws Exception
     */
    public function actionConfigure($clientSecretPath, $api = '')
    {

        $this->clientSecretPath = Yii::getAlias($clientSecretPath);
        if (!file_exists($this->clientSecretPath)) {
            throw new Exception("The client secret file \"{$this->clientSecretPath}\" does not exist!");
        }

        if (!$api || !isset($this->apis[$api])) {
            if ($api) {
                $this->stderr("Error: Unknown API requested: $api, prompting for the correct one..\n");
            }
            // prompt for the api to use
            $options = [];
            foreach ($this->apis as $id => $versions) {
                foreach ($versions as $version => $_api) {
                    $options[$id] = $_api->title;
                    break;
                }
            }
            $api = $this->select("\nPick an API to connect to", $options);
        }

        $version = false;
        if (count($this->apis[$api]) > 1) {
            if ($this->prompt("\nThe $api API has several versions. Install preferred version?")) {
                foreach ($this->apis[$api] as $_api) {
                    if ($_api->preferred) {
                        $version = $_api->version;
                    }
                }
            } else {
                $versions = [];
                foreach ($this->apis[$api] as $version => $_api) {
                    $versions[$version] = $version;
                }
                $version = $this->select("\nPick the desired version number", $versions);
            }
        } else {
            $version = array_keys($this->apis[$api])[0];
        }

        if ($version) {
            // Discover the API
            $discovery = $this->apis[$api][$version];

            $response = Json::decode(file_get_contents($discovery->discoveryRestUrl), false);

            $scopes = [];
            // Prompt for scopes if any
            if (isset($response->auth->oauth2->scopes)) {
                $this->stdout("\nAvailable scopes :\n");
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

                    if (!empty($scopes)) {
                        $done = true;
                    }
                }
            }

            $credentialsPath = $this->generateCredentialsFile($api, $scopes);
            $this->stdout(sprintf("Credentials saved to %s\n\n", $credentialsPath));

        } else {
            $this->stderr("Something went terribly wrong..\n");
        }
    }

    /**
     * List all the available APIs
     *
     * @param bool|false $showAllVersions Whether to show all versions of each API
     */
    public function actionList($showAllVersions = false)
    {
        foreach ($this->apis as $id => $versions) {
            foreach ($versions as $version => $api) {
                if (!$showAllVersions) {
                    $this->stdout($api->name . ' - ' . $api->title . "\n");
                    break;
                } else {
                    $this->stdout($api->id . ' - ' . $api->title . "\n");
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
     * Generates the credential file, prompting the user for the verification code
     * @param string $api The API name
     * @param Array $scopes The desired scopes
     * @return string
     */
    private function generateCredentialsFile($api, $scopes) {

        $credentialsPath = Yii::getAlias($this->configPath) . '/' . $api . '_' . $this->getUuid() . '.json';

        $client = new Google_Client();
        $client->setAuthConfigFile($this->clientSecretPath);
        $client->setAccessType('offline');
        $client->setScopes(implode(' ', $scopes));

        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        $this->stdout(sprintf("Open the following link in your browser:\n  %s\n", $authUrl));

        $authenticated = false;

        while (!$authenticated) {
            $authCode = $this->prompt("Enter the verification code: ");

            // Exchange authorization code for an access token.
            try {
                $accessToken = $client->authenticate($authCode);
                $authenticated = true;
            } catch (Google_Auth_Exception $e) {
                $this->stderr($e->getMessage() . "\n");
            }
        }

        // Store the credentials to disk.
        if (!is_dir(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        /** @var string $accessToken */
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
}
