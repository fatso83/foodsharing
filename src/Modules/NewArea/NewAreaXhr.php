<?php

namespace Foodsharing\Modules\NewArea;

use Foodsharing\Modules\Core\Control;

class NewAreaXhr extends Control
{
	public function __construct(NewAreaModel $model, NewAreaView $view)
	{
		$this->model = $model;
		$this->view = $view;

		parent::__construct();
	}

	public function orderFs()
	{
		if ($this->func->isOrgaTeam()) {
			if ((int)$_GET['bid'] == 0) {
				return array(
					'status' => 1,
					'script' => 'error("Du musst noch einen Bezirk auswählen in den die Foodsaver sortiert werden.");'
				);
			} else {
				$bezirk_id = (int)$_GET['bid'];
				$fsids = explode('-', $_GET['fs']);
				if (count($fsids) > 0) {
					$count = 0;
					$js = '';
					foreach ($fsids as $fid) {
						$fid = (int)$fid;
						if ($fid > 0) {
							++$count;
							$this->model->linkBezirk($fid, $bezirk_id);

							$foodsaver = $this->model->getValues(array('geschlecht', 'email', 'name', 'nachname'), 'foodsaver', $fid);
							$anrede = $this->func->genderWord($foodsaver['geschlecht'], 'Lieber', 'Liebe', 'Liebe/r');
							$name = $foodsaver['name'];

							$message = str_replace(array('{ANREDE}', '{NAME}'), array($anrede, $name), $_GET['msg']);

							$this->func->libmail(false, $foodsaver['email'], $_GET['subject'], $message);
							$this->model->clearWantNew($fid);

							$js .= '$(".wantnewcheck[value=\'' . $fid . '\']").parent().parent().remove();';
						}
					}

					return array(
						'status' => 1,
						'script' => 'pulseInfo("' . $count . ' E-Mails wurden versandt.");' . $js
					);
				}
			}
		}
	}

	public function deleteMarked()
	{
		if ($this->func->isOrgaTeam()) {
			$parts = explode('-', $_GET['del']);
			if (count($parts) > 0) {
				foreach ($parts as $p) {
					$this->model->clearWantNew($p);
				}
			}

			return array(
				'status' => 1
			);
		}
	}
}
