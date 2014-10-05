<?php
class MsgControl extends Control
{	
	public function __construct()
	{
		
		$this->model = new MsgModel();
		$this->view = new MsgView();
		
		parent::__construct();
		
		if(!S::may())
		{
			goLogin();
		}
		
	}
	
	public function index()
	{
		$this->setTemplate('msg');
		
		addJs('msg.fsid = '.(int)fsId().';');
		/*
		 * later:
		 * // list Conversations
		 */
		addBread(s('messages'));
		addTitle(s('messages'));
		
		//addContent($this->view->top());
		
		addContent($this->view->compose());
		addContent($this->view->conversation());
		addContent($this->view->leftMenu(),CNT_RIGHT);
		
		$conversations = false;
		if($conversations = $this->model->listConversations())
		{
			$ids = array();
			foreach ($conversations as $c)
			{
				$ids[$c['id']] = true;
			}
			S::set('msg_conversations', $ids);
		}
		addContent($this->view->conversationList($conversations),CNT_RIGHT);
		
	}
}