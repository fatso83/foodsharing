<?php

namespace Foodsharing\Modules\PassportGenerator;

use Foodsharing\Modules\Core\View;

final class PassportGeneratorView extends View
{
	public function passTable(array $region): string
	{
		$data = array();

		foreach ($region['foodsaver'] as $fs) {
			$last = '<span style="display:none">a0</span> <a href="#" class="dateclick linkrow ui-corner-all"> - ' . $this->translationHelper->s('never_generated') . ' - </a>';
			if ($fs['last_pass'] != '0000-00-00 00:00:00' && $fs['last_pass'] != null) {
				$last = '<span style="display:none">a' . date('YmdHis', $fs['last_pass_ts']) . '</span> <a href="#" class="dateclick linkrow ui-corner-all">' . $this->timeHelper->niceDate($fs['last_pass_ts']) . '</a>';
			}

			$verified = '<span style="display:none">b</span><a title="' . $this->translationHelper->sv('click_to_verify', $fs['name']) . '" href="#" class="verify verify-n"><span></span></a>';
			if ($fs['verified']) {
				$verified = '<span style="display:none">a</span><a href="#" title="' . $this->translationHelper->s('click_to_unverify') . '" class="verify verify-y"><span></span></a>';
			}

			if (!empty($fs['photo'])) {
				$img = 'images/thumb_crop_' . $fs['photo'];
			} else {
				$img = $this->imageService->img($fs['photo']);
			}

			$data[] = [
				['cnt' => '<input class="checkbox bezirk' . $region['id'] . ' date' . date('Y-m-d-H-i-s', $fs['last_pass_ts']) . '" type="checkbox" name="foods[]" value="' . $fs['id'] . '" />'],
				['cnt' => '<span style="display:none">a' . $fs['photo'] . '</span><a href="#" class="fsname"><img src="' . $img . '" width="35" /></a>'],
				['cnt' => '<a href="/?page=foodsaver&a=edit&id=' . $fs['id'] . '" class="linkrow ui-corner-all">' . $fs['name'] . '</a>'],
				['cnt' => $last],
				['cnt' => $verified]
			];
		}

		return
			$this->v_utils->v_field(
				$this->v_utils->v_tablesorter(
					[
					['name' => '<input class="checker" type="checkbox" name="checker" value="' . $region['id'] . '" />', 'sort' => false, 'width' => 20],
					['name' => $this->translationHelper->s('photo'), 'width' => 40],
					['name' => $this->translationHelper->s('name')],
					['name' => $this->translationHelper->s('last_generated'), 'width' => 200],
					['name' => $this->translationHelper->s('verified'), 'width' => 70]
					], $data),

				$region['bezirk']
			);
	}

	public function menubar(): string
	{
		return $this->v_utils->v_menu(
			[
			['name' => 'Alle markieren', 'click' => 'checkAllCb(true);return false;'],
			['name' => 'Keine markieren', 'click' => 'checkAllCb(false);return false;']
			], $this->translationHelper->s('options'));
	}

	public function start(): string
	{
		return $this->v_utils->v_menu(
			[
			['name' => 'Markierte Ausweise generieren', 'href' => '#start']
			], $this->translationHelper->s('start'));
	}

	public function tips(): string
	{
		return $this->v_utils->v_info($this->translationHelper->s('tips_content'), $this->translationHelper->s('tips'));
	}
}
