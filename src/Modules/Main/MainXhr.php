<?php

namespace Foodsharing\Modules\Main;

use fImage;
use Foodsharing\Modules\Core\Control;
use fUpload;

class MainXhr extends Control
{
	public function __construct()
	{
		parent::__construct();
	}

	public function picupload()
	{
		$function = '';
		$newname = '';

		$check = false;

		if (isset($_FILES['picture']) && (int)$_FILES['picture']['size'] > 0) {
			$id = strip_tags($_POST['id']);
			$inid = strip_tags($_POST['inid']);
			$upload = new fUpload();
			$upload->setMIMETypes(
				array(
					'image/gif',
					'image/jpeg',
					'image/pjpeg',
					'image/png'
				),
				s('no_image')
			);
			try {
				$file = $upload->move('tmp', 'picture');
				$newname = uniqid() . '.' . strtolower($file->getExtension());
				rename($file->getPath(), 'tmp/' . $newname);
				copy('tmp/' . $newname, 'tmp/thumb-' . $newname);
				$thumb = new fImage('tmp/thumb-' . $newname);
				$thumb->cropToRatio(1, 1);
				$thumb->resize(250, 250);

				$function = 'placeThumb();';
				$check = true;
			} catch (Exception $e) {
				$check = false;
			}
		}

		if (!$check) {
			$function = 'window.parent.pulseError(\'Sorry, Dieses Foto konnte nicht verarbeitet werden.\');window.parent.$(\'.attach-preview\').hide();';
		}

		echo '<html><head><title>upload</title>
		<script type="text/javascript">
			function placeThumb()
			{
				window.parent.$(".ui-dialog-buttonpane .ui-button").button( "option", "disabled", false );
				window.parent.$(\'#' . $inid . '-filename\').val(\'' . $newname . '\');
				window.parent.$(\'.attach-preview\').html(\'<a href="#" onclick="return false;" class="preview-thumb" rel="wallpost-gallery"><img height="60" src="/tmp/thumb-' . $newname . '">&nbsp;</a><div style="clear:both"></div>\');
			}
		</script>
		</head><body onload="' . $function . '"></body></html>';
		exit();
	}
}