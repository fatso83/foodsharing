<?php
class ReportControl extends Control
{	
	public function __construct()
	{
		if(!isset($_GET['sub']))
		{
			go('?page=report&sub=uncom');
		}
		$this->model = new ReportModel();
		$this->view = new ReportView();
		
		parent::__construct();
		
	}
	
	public function index()
	{
		if(S::may('orga') || isBotFor(347))
		{
			addBread('Reportmeldungen','?page=report');
		}
		else
		{
			go('?page=dashboard');
		}
	}
	
	public function uncom()
	{
		addContent($this->view->statsMenu($this->model->getReportStats()),CNT_LEFT);
		addContent($this->view->listReportedSavers($this->model->getReportedSavers()),CNT_LEFT);
		
		$reports = array();
		if($reports = $this->model->getReports(0))
		{
			addContent($this->view->listReports($reports));
		}
		addContent($this->view->topbar('Neue Verstoßmeldungen', count($reports).' gesamt', '/img/shit.png'),CNT_TOP);
	}
	
	public function com()
	{
		addContent($this->view->statsMenu($this->model->getReportStats()),CNT_LEFT);
		addContent($this->view->listReportedSavers($this->model->getReportedSavers()),CNT_LEFT);
		
		$reports = array();
		if($reports = $this->model->getReports(1))
		{
			addContent($this->view->listReports($reports));
		}
		addContent($this->view->topbar('Bestätigte Verstoßmeldungen', count($reports).' gesamt', '/img/shit.png'),CNT_TOP);
	}
	
	public function foodsaver()
	{
		if($this->may())
		{
			if($foodsaver = $this->model->getReportedSaver($_GET['id']))
			{
				addBread('Reportmeldungen','?page=report&sub=foodsaver&id='.(int)$foodsaver['id']);
				addJs('
						$(".welcome_profile_image").css("cursor","pointer");
						$(".welcome_profile_image").click(function(){
							$(".user_display_name a").trigger("click");
						});
				');
				addContent($this->view->topbar('Meldungen von <a href="#" onclick="profile('.(int)$foodsaver['id'].');return false;">'.$foodsaver['name'].' '.$foodsaver['nachname'].'</a>', count($foodsaver['reports']).' gesamt', img($foodsaver['photo'])),CNT_TOP);
				addContent(
						v_field($this->wallposts('fsreport', (int)$_GET['id']),'Notizen')
					);
				addContent($this->view->listReportsTiny($foodsaver['reports']),CNT_RIGHT);
			}
		}
		else
		{
			go('?page=dashboard');
		}
	}
	
	public function may()
	{
		if(isOrgaTeam() || isBotFor(347))
		{
			return true;
		}
		return false;
	}
}