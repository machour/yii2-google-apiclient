<div class="google_apiclient-default-index">
    <h1>GoogleApiClient</h1>

    <p>Example displaying your last GMail message subject</p>
        <?php highlight_string(<<<CODE
        <?php

        \$service = Yii::\$app->gmail->getService();

        // Print the labels in the user's account.
        \$user = 'me';

        \$messages = \$service->users_messages->listUsersMessages('me', [
            'maxResults' => 1,
            'labelIds' => 'INBOX',
        ]);
        \$list = \$messages->getMessages();

        if (count(\$list) == 0) {
            echo "You have no emails in your INBOX .. how did you achieve that ??";
        } else {
            \$messageId = \$list[0]->getId(); // Grab first Message

            \$message = \$service->users_messages->get('me', \$messageId, ['format' => 'full']);

            \$messagePayload = \$message->getPayload();
            \$headers = \$messagePayload->getHeaders();

            echo "Your last email subject is: ";
            foreach (\$headers as \$header) {
                if (\$header->name == 'Subject') {
                    echo "<b>" . \$header->value . "</b>";
                }
            }

        }
CODE
); ?>
    <p>
        <?php

        /**
         * @var $service Google_Service_Gmail
         */
        $service = Yii::$app->gmail->getService();

        // Print the labels in the user's account.
        $user = 'me';

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
        ?>
    </p>
</div>
