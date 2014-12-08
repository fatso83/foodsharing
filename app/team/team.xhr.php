<?php 
class TeamXhr extends Control
{
	
	public function __construct()
	{
		$this->model = new TeamModel();
		$this->view = new TeamView();

		parent::__construct();
	}
	
	public function contact()
	{
		$xhr = new Xhr();
		
		if($id = $this->getPostInt('id'))
		{
			if($user = $this->model->getUser($id))
			{
				$mail = new SocketMail();
				
				if(validEmail($_POST['email']))
				{
					$mail->setFrom($_POST['email']);
				}
				else
				{
					$mail->setFrom(DEFAULT_EMAIL);
				}
				
				$msg = strip_tags($_POST['message']);
				
				$msg = 'Name: ' . strip_tags($_POST['name']) . "\n\n" . $msg;
				
				$mail->setBody($message);
				$mail->setHtmlBody(nl2br($message));
				
				$mail->addRecipient($user['email']);
				
				$socket = new SocketClient();
				$socket->queue($mail);
				$socket->send();
				
				$xhr->addMessage(s('mail_send_success'),'success');
				$xhr->send();
			}
		}
		
		$xhr->addMessage(s('error'),'error');
		$xhr->send();
	}
}