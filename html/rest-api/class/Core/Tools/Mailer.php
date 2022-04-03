<?php
/**
 * Class HTML Mailer
 *
 * Handles all mailing tasks.
 *
 * @author 	Gregor Müller-Elmau @ schalk&friends
 * @since  	03/2022
 * @package Rest API
 */

namespace RestAPI\Core\Tools;


use RestAPI\Core\ErrorResponse;

class Mailer {

	private $sender_name		= '';
	private $sender_email		= '';
	private $subject			= 'No subject';
	private $preheader			= '';
	private $mail_layout		= '';
	private $mail_body			= '';


	public function __construct(string $template, array $params, string $recipient_name, string $recipient_email){
		global $api_settings;

		$this->load_template($template);

		$recipient 				= $recipient_name? sprintf('%s <%s>', $recipient_name, $recipient_email) : $recipient_email;
		$params['template']		= $template;
		$this->sender_name 		= $api_settings['email.sender.name'];
		$this->sender_email 	= $api_settings['email.sender.mail'];


		foreach($params as $key => $val){
			if(is_string($val)){
				$this->subject = str_replace('{{'.$key.'}}', $val, $this->subject);
				$this->preheader = str_replace('{{'.$key.'}}', $val, $this->preheader);
				$this->mail_layout = str_replace('{{'.$key.'}}', $val, $this->mail_layout);
				$this->mail_body = str_replace('{{'.$key.'}}', $val, $this->mail_body);
			}
		}

		$html_body = str_replace('{{preheader}}', $this->preheader, $this->mail_layout);
		$html_body = str_replace('{{mail_body}}', $this->mail_body, $html_body);

		$headers = array(
			'MIME-Version: 1.0',
			'Content-Type: text/html; charset=utf-8',
			sprintf('From: %s <%s>', $this->sender_name, $this->sender_email),
			sprintf('Reply-To: %s', $api_settings['email.replyTo']),
			sprintf('Return-Path: %s', $api_settings['email.returnPath']),
		);


		// TODO: add custom headers, CC , BCC by filter or hook??


		$done = mail($recipient, $this->subject, $html_body, implode(PHP_EOL, $headers));

		if($done){
			Logfile::success(sprintf('Mail submitted for %s', $recipient));
		}else{
			Logfile::error(sprintf('Failed to submit the mail for %s', $recipient));
		}
	}


	private function load_template(string $template): void {

		$template_layout 	= REST_API_DIR.'/templates/email/layout.html';
		$template_path		= REST_API_DIR.'/templates/email/'.$template.'.html';

		if(!file_exists($template_layout)){
			new ErrorResponse('missing_template', 'The mail layout template is missing');
		}
		if(!file_exists($template_path)){
			new ErrorResponse('missing_template', sprintf('The mail template %s is missing', $template));
		}

		$this->mail_layout 	= file_get_contents($template_layout);
		$template_string = file_get_contents($template_path);

		if(preg_match_all("/<!--([^-]+)-->(.*?(?=<!--|$))/s", $template_string, $matches)){
			// 0 string
			// 1 section name ... apply strtolower()
			// 2 content .. apply trim()

			foreach($matches[1] as $key => $name){

				$name = str_replace(' ', '-', strtolower(trim($name)));

				switch($name){
					case 'subject':
						$this->subject = trim($matches[2][$key]);
						break;
					case 'preheader':
						$this->preheader = trim($matches[2][$key]);
						break;
					case 'html-body':
						$this->mail_body = trim($matches[2][$key]);
						break;
				}
			}
		}



	}


}
