# yii2-google-apiclient

A Yii2 wrapper for the official Google API PHP Client, featuring:

* Console utility to generate your credentials files
* A component that will take care of the oAuth phases

This package will also install the [google/apiclient](http://github.com/google/apiclient) library.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist machour/yii2-google-apiclient "*"
```

or add

```
"machour/yii2-google-apiclient": "*"
```

to the require section of your `composer.json` file.


Configuration
-------------

**Credentials file**

In order to use this extension, you will be needing a credentials file for your Google Application.

You can optionally generate this file using the provided console utility:

* Configure the module in `config/console.php`:
```php
'bootstrap' => ['log', 'google_apiclient'],
'modules' => [
    'google_apiclient' => [
        'class' => 'machour\yii2\google\apiclient\Module',
    ],
],
```

* Use the /configure sub command:
```shell 
./yii google_apiclient/configure <clientSecretPath> [api]
```

where `clientSecretPath` is the path to your secret JSON file obtained from the [Google Console](https://console.developers.google.com/) and `api` the api identifier (it will be prompted for if not provided).


**Components**

You application may use as much Google_Service instances as you need, by adding an entry into the `components` index of the Yii configuration array.

Here's how to setup GMail for example, a usage sample is provided below.

```php
    'components' => [
        // ..
        'gmail' => [
            'class' => 'machour\yii2\google\apiclient\components\GoogleApiClient',
            'credentialsPath' => '@runtime/google-apiclient/auth.json',
            'clientSecretPath' => '@runtime/google-apiclient/gmail.json',
            'api' => Google_Service_Gmail::class,
        ],
```

This will enable you to access the GMail authenticated service `Yii::$app->gmail->getService()` in your application.

Usage
-----

**Displaying your newest message subject on GMail**

```php
/**
 * @var $service Google_Service_Gmail
 */
$service = Yii::$app->gmail->getService();

$messages = $service->users_messages->listUsersMessages('me', [
    'maxResults' => 1,
    'labelIds' => 'INBOX',
]);
$list = $messages->getMessages();

if (count($list) == 0) {
    echo "You have no emails in your INBOX .. how did you achieve that ??";
} else {
    $messageId = $list[0]->getId(); // Grab first Message

    $message = $service->users_messages->get('me', $messageId, ['format' => 'full']);

    $messagePayload = $message->getPayload();
    $headers = $messagePayload->getHeaders();

    echo "Your last email subject is: ";
    foreach ($headers as $header) {
        if ($header->name == 'Subject') {
            echo "<b>" . $header->value . "</b>";
        }
    }

}
```