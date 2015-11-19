<?php

/**
 * A minimalistic Emailer class. Creates and sends HTML emails.
 *
 * @author Andreas kre Solberg, UNINETT AS. <andreas.solberg@uninett.no>
 * @package simpleSAMLphp
 * @version $Id$
 */
class SimpleSAML_XHTML_EMail {


	private $to = NULL;
	private $cc = NULL;
	private $body = NULL;
	private $from = NULL;
	private $replyto = NULL;
	private $subject = NULL;
	private $headers = array();
	

    private $smptparams = NULL;
	/**
	 * Constructor
	 */
	function __construct($to, $subject, $from = NULL, $cc = NULL, $replyto = NULL, $smtp_params = NULL) {
		$this->to = $to;
		$this->cc = $cc;
		$this->from = $from;
		$this->replyto = $replyto;
		$this->subject = $subject;
        
        $this->smptparams = $smtp_params;
	}

	function setBody($body) {
		$this->body = $body;
	}
	
	private function getHTML($body) {
		return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<title>simpleSAMLphp Email report</title>
	<style type="text/css">
pre, div.box {
	margin: .4em 2em .4em 1em;
	padding: 4px;

}
pre {
	background: #eee;
	border: 1px solid #aaa;
}
	</style>
</head>
<body>
<div class="container" style="background: #fafafa; border: 1px solid #eee; margin: 2em; padding: .6em;">
' . $body . '
</div>
</body>
</html>';
	}

	function send() {
		if ($this->to == NULL) throw new Exception('EMail field [to] is required and not set.');
		if ($this->subject == NULL) throw new Exception('EMail field [subject] is required and not set.');
		if ($this->body == NULL) throw new Exception('EMail field [body] is required and not set.');
		
		$random_hash = SimpleSAML_Utilities::stringToHex(SimpleSAML_Utilities::generateRandomBytes(16));
		
		if (isset($this->from))
			$this->headers[]= 'From: ' . $this->from;
		if (isset($this->replyto))
			$this->headers[]= 'Reply-To: ' . $this->replyto;

		$this->headers[] = 'Content-Type: multipart/alternative; boundary="simplesamlphp-' . $random_hash . '"'; 
		
		$message = '
--simplesamlphp-' . $random_hash . '
Content-Type: text/plain; charset="utf-8" 
Content-Transfer-Encoding: 8bit

' . strip_tags(html_entity_decode($this->body)) . '

--simplesamlphp-' . $random_hash . '
Content-Type: text/html; charset="utf-8" 
Content-Transfer-Encoding: 8bit

' . $this->getHTML($this->body) . '

--simplesamlphp-' . $random_hash . '--
';
		
        if($this->smptparams) {
         
            require_once("Mail.php");
            require_once("Mail/mime.php");
            $mailer = @Mail::factory("smtp", $this->smptparams);
            if (@PEAR::isError($mailer))
            {
                SimpleSAML_Logger::debug("Failed to create smpt mailer. Please ensure that the Mail PEAR package is installed correctly.");
                throw new Exception('Error when sending e-mail');
            } else {
                
                $From = $this->from; 
                $To = $this->to;
              
                $headers["From"] = $From; 
                $headers["To"] = $To; 
                //$headers["Subject"] = $this->subject;
                if(isset($this->replyto))
                    $headers["Reply-To"] = $this->replyto;
                //$headers["Content-Type"] = "text/plain; charset=utf-8";
                
                
                
				$mail_composer = new Mail_mime();

				$mail_composer->setFrom($From);

				if (method_exists($mail_composer, 'addTo'))
				{
					$mail_composer->addTo($To);
				}
				else
				{
					$additional_headers["To"] = $to;
				}
                
				$mail_composer->setSubject($this->subject);
				$mail_composer->setHtmlBody($message);
                
                $params['text_charset'] = 'utf-8';
				$params['html_charset'] = 'utf-8';
				$params['head_charset'] = 'utf-8';

				$mail_body = $mail_composer->get($params);
				$mail_headers = $mail_composer->headers($headers);
                
                $result = @$mailer->send($this->to, $mail_headers, $mail_body);
                

                
                
				if (@PEAR::isError($result)) {
					$log_message = "\r====================FAILED TO SEND EMAIL==============";
					$log_message .= "\r	time: " . gmdate("Y-m-d H:i:s");
					$log_message .= "\r	recipients: " . print_r($this->to, true);
					$log_message .= "\r	headers: " . print_r($mail_headers, true);
					$log_message .= "\r	body: " . print_r($mail_body, true);
					$log_message .= "\r	result: " . print_r($result, true);
					$log_message .= "\r=====================================================\r";
                    SimpleSAML_Logger::debug("Failed to send email: ".$log_message);
                    throw new Exception('Error when sending e-mail');
                }
            }
        } else {
            $headers = implode("\n", $this->headers);
            $mail_sent = @mail($this->to, $this->subject, $message, $headers);
            SimpleSAML_Logger::debug('Email: Sending e-mail to [' . $this->to . '] : ' . ($mail_sent ? 'OK' : 'Failed'));
            if (!$mail_sent) throw new Exception('Error when sending e-mail');
        }
	}

}

?>