<?php

namespace Foodsharing\Helpers;

use Flourish\fFile;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Mail\AsyncMail;
use Foodsharing\Modules\Core\InfluxMetrics;
use Foodsharing\Services\SanitizerService;
use Twig\Environment;

final class EmailHelper
{
	private $mem;
	/**
	 * @var InfluxMetrics
	 */
	private $metrics;
	private $emailTemplateAdminGateway;
	private $sanitizerService;
	private $twig;

	public function __construct(
		InfluxMetrics $metrics,
		Mem $mem,
		SanitizerService $sanitizerService,
		Environment $twig
	) {
		$this->mem = $mem;
		$this->metrics = $metrics;
		$this->sanitizerService = $sanitizerService;
		$this->twig = $twig;
	}

	private function emailBodyTpl(string $message, $email = false, $token = false): string
	{
		$unsubscribe = $this->twig->render('emailTemplates/general/unsubscribe.html.twig', []);

		if ($email !== false && $token !== false) {
			$unsubscribe = $this->twig->render('emailTemplates/general/unsubscribe_newsletter.html.twig', array('TOKEN' => $token, 'EMAIL' => $email));
		}

		$message = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $message);

		$search = array('<a', '<td', '<li');
		$replace = array('<a style="color:#F36933"', '<td style="font-size:13px;font-family:Arial;color:#31210C;"', '<li style="margin-bottom:11px"');
		$message = str_replace($search, $replace, $message);

		return $this->twig->render('emailTemplates/general/body.html.twig', array('MESSAGE' => $message, 'UNSUBSCRIBE' => $unsubscribe));
	}

	public function tplMail($tpl_id, $to, $var = array(), $from_email = false)
	{
		$mail = new AsyncMail($this->mem);

		if ($from_email !== false && $this->validEmail($from_email)) {
			$mail->setFrom($from_email);
		} else {
			$emailName = '';
			if (array_key_exists('sender', $var)) {
				$emailName = $var['sender'];
			} elseif (array_key_exists('poster', $var)) {
				$emailName = $var['poster'];
			}
			if (array_key_exists('bezirk', $var)) {
				$emailName .= ' in ' . $var['bezirk'];
			}
			if ($emailName !== '') {   // if sender information is present
				$emailName .= ' via '; // though this is optional...
			}
			$emailName .= DEFAULT_EMAIL_NAME;
			$mail->setFrom(DEFAULT_EMAIL, $emailName);
		}

		$locale = 'de-de';
		$tpl_prefix = 'emailTemplates/' . $tpl_id . '.' . $locale;
		$var = array_change_key_case($var, CASE_UPPER);
		$message = array(
			'subject' => $this->twig->render($tpl_prefix . '.subject.twig', $var),
			'body' => $this->twig->render($tpl_prefix . '.body.html.twig', $var)
		);

		$htmlBody = $this->emailBodyTpl($message['body']);
		$mail->setHTMLBody($htmlBody);

		// playintext body
		$plainBody = $this->sanitizerService->htmlToPlain($htmlBody);
		$mail->setBody($plainBody);

		if (!$message['subject']) {
			$message['subject'] = 'foodsharing-Mail: {EXCERPT}';
		}

		if (mb_strpos($message['subject'], '{EXCERPT}') !== false) {
			$plainMessage = $this->sanitizerService->htmlToPlain($message['body']);
			$subjectLength = mb_strlen($message['subject']) - strlen('{EXCERPT}');
			/* RFC recommends 78 characters for subjects */
			$excerpt = $this->sanitizerService->tt($plainMessage, 78 - $subjectLength);
			$message['subject'] = str_replace('{EXCERPT}', $excerpt, $message['subject']);
		}

		$mail->setSubject($message['subject']);

		if (is_iterable($to)) {
			foreach ($to as $recipient) {
				$mail->addRecipient($recipient);
			}
		} else {
			$mail->addRecipient($to);
		}
		$mail->send();
		$this->metrics->addPoint('outgoing_email', ['template' => $tpl_id], ['count' => 1]);
	}

	public function validEmail(string $email): bool
	{
		if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return true;
		}

		return false;
	}

	public function libmail($bezirk, $email, $subject, $message, $attach = false, $token = false)
	{
		if ($bezirk === false) {
			$bezirk = array(
				'email' => DEFAULT_EMAIL,
				'email_name' => DEFAULT_EMAIL_NAME
			);
		} elseif (!is_array($bezirk)) {
			$bezirk = array(
				'email' => $bezirk,
				'email_name' => $bezirk
			);
		} else {
			if (!$this->validEmail($bezirk['email'])) {
				$bezirk['email'] = EMAIL_PUBLIC;
			}
			if (empty($bezirk['email_name'])) {
				$bezirk['email_name'] = EMAIL_PUBLIC_NAME;
			}
		}

		if (!$this->validEmail($email)) {
			return false;
		}

		$mail = new AsyncMail($this->mem);
		$mail->setFrom($bezirk['email'], $bezirk['email_name']);
		$mail->addRecipient($email);
		if (!$subject) {
			$subject = 'foodsharing-Mail';
		}
		$mail->setSubject($subject);
		$htmlBody = $this->emailBodyTpl($message, $email, $token);
		$mail->setHTMLBody($htmlBody);

		$plainBody = $this->sanitizerService->htmlToPlain($htmlBody);
		$mail->setBody($plainBody);

		if ($attach !== false) {
			foreach ($attach as $a) {
				$mail->addAttachment(new fFile($a['path']), $a['name']);
			}
		}

		$mail->send();
	}
}
