<?php

require_once(__DIR__.'/Util.php');

class BlobMessage {
    public $sender = "";
    public $message = "";

    function __construct($sender, $message) {
        $this->sender = $sender;
        $this->message = $message;
    }
}

class RestAPI {
    // public $urlPost = "https://productlive.servicebus.windows.net/connectorsqueuedev/messages?timeout=60";
    // public $urlGet = "https://productlive.servicebus.windows.net/topic/subscriptions/allmessages/messages/head?timeout=10";
    // public $urlDelete = "https://productlive.servicebus.windows.net/topic/subscriptions/allmessages/messages/";
    // public $urlBlob = "http://dev-productlive-connectors.azurewebsites.net/rest/message";

    public $urlPost = "https://productlive.servicebus.windows.net/connectorsqueue/messages?timeout=60";
    public $urlGet = "https://productlive.servicebus.windows.net/topic/subscriptions/allmessages/messages/head?timeout=10";
    public $urlDelete = "https://productlive.servicebus.windows.net/topic/subscriptions/allmessages/messages/";
    public $urlBlob = "http://productlive-connectors.azurewebsites.net/rest/message";

    public $sendMessageToken = "";
    public $sender = "";
    public $receiveMessageToken = "";

    function __construct()
    {
        // Product-Live config
        $productLiveConfig = parse_ini_file(__DIR__."/../config.ini");
        $this->sendMessageToken = $productLiveConfig['sendMessageToken'];
        $this->sender = $productLiveConfig['sender'];
        $this->receiveMessageToken = $productLiveConfig['receiveMessageToken'];
        $this->topic = $productLiveConfig['topic'];
        $this->subscription = $productLiveConfig['subscription'];
    }

    function postMessage($message, $flux, $action) {
        $productLiveConfig = parse_ini_file(__DIR__."/../config.ini");
        $sendMessageToken = $productLiveConfig['sendMessageToken'];
        $sender = $productLiveConfig['sender'];
        $content_json = json_encode($message, JSON_UNESCAPED_UNICODE);

        $messageSize = strBytes($content_json);

        $result  = array( );

        if ($messageSize > 200000) {    //200000 max for azure
            $content = new BlobMessage($sender, $message);
            $content_json_blob = json_encode($content, JSON_UNESCAPED_UNICODE);
            $headers = array(
                'http' => array(
                    'method' => "POST",
                    'header' => "Content-type: application/json" . "\r\n" .
                        "Content-Length: " . strlen($content_json_blob) . "\r\n",
                    'content' => $content_json_blob
                )
            );

            // Creates a stream context
            $context = stream_context_create($headers);

            // Open the URL with the HTTP headers (fopen wrappers must be enabled)
            $page = @file_get_contents($this->urlBlob, false, $context); //fopen($url, 'r', false, $context);

            
            if ( $page != false )
                $result['content'] = $page;
            else if ( !isset( $http_response_header ) )
                return null;    // Bad url, timeout

            // Save the header
            $result['header'] = $http_response_header;

            // Get the *last* HTTP status code
            $nLines = count( $http_response_header );
            for ( $i = $nLines-1; $i >= 0; $i-- )
            {
                $line = $http_response_header[$i];
                if ( strncasecmp( "HTTP", $line, 4 ) == 0 )
                {
                    $response = explode( ' ', $line );
                    $result['http_code'] = $response[1];
                    break;
                }
            }

            if ($result['http_code']=="200") {
                $res = json_decode($result['content'], true);
                if ($res['success']==true) {
                    $messageName = $res['messageName'];

                    // Set the HTTP request authentication headers
                    $headers = array(
                        'http' => array(
                            'method' => "POST",
                            'header' => "Content-type: application/atom+xml;type=entry;charset=utf-8" . "\r\n" .
                                "Authorization: " . $sendMessageToken . "\r\n" .
                                "priority: 50" . "\r\n" .
                                "file: true" . "\r\n" .
                                "action: " . $action . "\r\n" .
                                "sender: " . $sender . "\r\n" .
                                "messageVersion: 1.0" . "\r\n" .
                                "flux: " . $flux . "\r\n" .
                                "attemptremaining: 1" . "\r\n" .
                                "Content-Length: " . strlen($messageName) . "\r\n",
                            'content' => $messageName
                        )
                    );

                    // Creates a stream context
                    $context = stream_context_create($headers);

                    // Open the URL with the HTTP headers (fopen wrappers must be enabled)
                    $page = @file_get_contents($this->urlPost, false, $context); //fopen($url, 'r', false, $context);

                    $result  = array( );
                    if ( $page != false )
                        $result['content'] = $page;
                    else if ( !isset( $http_response_header ) )
                        return null;    // Bad url, timeout

                    // Save the header
                    $result['header'] = $http_response_header;

                    // Get the *last* HTTP status code
                    $nLines = count( $http_response_header );
                    for ( $i = $nLines-1; $i >= 0; $i-- )
                    {
                        $line = $http_response_header[$i];
                        if ( strncasecmp( "HTTP", $line, 4 ) == 0 )
                        {
                            $response = explode( ' ', $line );
                            $result['http_code'] = $response[1];
                            break;
                        }
                    }
                }
            }

        } else {
            // Set the HTTP request authentication headers
            $headers = array(
                'http' => array(
                    'method' => "POST",
                    'header' => "Content-type: application/atom+xml;type=entry;charset=utf-8" . "\r\n" .
                        "Authorization: " . $sendMessageToken . "\r\n" .
                        "priority: 50" . "\r\n" .
                        "action: " . $action . "\r\n" .
                        "sender: " . $sender . "\r\n" .
                        "messageVersion: 1.0" . "\r\n" .
                        "flux: " . $flux . "\r\n" .
                        "attemptremaining: 1" . "\r\n" .
                        "Content-Length: " . strlen($content_json) . "\r\n",
                    'content' => $content_json
                )
            );

            // Creates a stream context
            $context = stream_context_create($headers);

            // Open the URL with the HTTP headers (fopen wrappers must be enabled)
            $page = @file_get_contents($this->urlPost, false, $context); //fopen($url, 'r', false, $context);

            if ( $page != false )
                $result['content'] = $page;
            else if ( !isset( $http_response_header ) )
                return null;    // Bad url, timeout

            // Save the header
            $result['header'] = $http_response_header;

            // Get the *last* HTTP status code
            $nLines = count( $http_response_header );
            for ( $i = $nLines-1; $i >= 0; $i-- )
            {
                $line = $http_response_header[$i];
                if ( strncasecmp( "HTTP", $line, 4 ) == 0 )
                {
                    $response = explode( ' ', $line );
                    $result['http_code'] = $response[1];
                    break;
                }
            }
        }        

        return $result;
    }

    function getMessage() {
        // Set the HTTP request authentication headers
        $headers = array(
            'http' => array(
                'method' => "POST",
                'header' => "Content-type: application/atom+xml;type=entry;charset=utf-8" . "\r\n" .
                    "Authorization: " . $this->receiveMessageToken  . "\r\n" .
                    "Content-Length: 0"
            )
        );

        // Creates a stream context
        $context = stream_context_create($headers);

        // Get url
        $service_url_get = str_replace("topic", $this->topic, $this->urlGet);
        $service_url_get = str_replace("allmessages", $this->subscription, $service_url_get);

        // Open the URL with the HTTP headers (fopen wrappers must be enabled)
        $page = @file_get_contents($service_url_get, false, $context); //fopen($url, 'r', false, $context);
        //$page = @fopen($service_url_get, 'r', false, $context);
  
        $result  = array( );
        if ( $page != false )
            $result['content'] = $page;
        else if ( !isset( $http_response_header ) )
            return null;    // Bad url, timeout

        // Save the header
        $result['header'] = $http_response_header;

        // Get the *last* HTTP status code
        $nLines = count( $http_response_header );
        for ( $i = $nLines-1; $i >= 0; $i-- )
        {
            $line = $http_response_header[$i];
            if ( strncasecmp( "HTTP", $line, 4 ) == 0 )
            {
                $response = explode( ' ', $line );
                $result['http_code'] = $response[1];
                break;
            }
        }

        return $result;
    }

    function deleteMessage($messageId, $lockToken) {
        // Déverouiller le message puis le supprimer
        // Set the HTTP request authentication headers
        $headers = array(
            'http' => array(
                'method' => "DELETE",
                'header' => "Content-type: application/atom+xml;type=entry;charset=utf-8" . "\r\n" .
                    "Authorization: " . $this->receiveMessageToken . "\r\n" .
                    "Content-Length: 0"
            )
        );

        // Creates a stream context
        $context = stream_context_create($headers);

        // Get url
        $service_url_delete = str_replace("topic", $this->topic, $this->urlDelete);
        $service_url_delete = str_replace("allmessages", $this->subscription, $service_url_delete);
        $service_url_delete = $service_url_delete.$messageId."/".$lockToken;
        //echo $service_url_delete."<br>";

        //echo $service_url_get;

        // Open the URL with the HTTP headers (fopen wrappers must be enabled)
        $page = @file_get_contents($service_url_delete, false, $context); //fopen($url, 'r', false, $context);

        $result  = array( );
        if ( $page != false )
            $result['content'] = $page;
        else if ( !isset( $http_response_header ) )
            return null;    // Bad url, timeout

        // Save the header
        $result['header'] = $http_response_header;

        // Get the *last* HTTP status code
        $nLines = count( $http_response_header );
        for ( $i = $nLines-1; $i >= 0; $i-- )
        {
            $line = $http_response_header[$i];
            if ( strncasecmp( "HTTP", $line, 4 ) == 0 )
            {
                $response = explode( ' ', $line );
                $result['http_code'] = $response[1];
                break;
            }
        }

        return $result;
    }

}