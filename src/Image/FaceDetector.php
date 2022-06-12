<?php

namespace Xeno\Image;

class FaceDetector
{
	private static $detection = null;
	private $im;

	public function __construct($img) {
		if(!self::$detection) {
			self::$detection = json_decode(file_get_contents(__DIR__.'/detection.json'));
		}
		if(is_resource($img)) $this->im = $img;
		elseif(is_file($img)) {
			if(false === ($mime = getimagesize($img))) {
				throw new \ErrorException('not image. '.$mime['mime']);
			}
			switch($mime['mime']) {
				case 'image/jpeg':
					$this->im = imagecreatefromjpeg($img);
					break;
				case 'image/png':
					$this->im = imagecreatefrompng($img);
					break;
				case 'image/gif':
					$this->im = imagecreatefromgif($img);
					break;
				default:
					throw new \ErrorException('unsupported format. '.$mime['mime']);
			}
		} elseif(is_string($img)) {
			$this->im = imagecreatefromstring($img);
		} else {
			throw new \ErrorException('unknown. '.print_r($img, true));
		}
	}

	public function __destruct() {
		imagedestroy($this->im);
	}

	public function getImageSize() {
		return ['width' => imagesx($this->im), 'height' => imagesy($this->im)];
	}

	public function getImage() {
		return $this->im;
	}

	public function FaceDetect($multiple = null, $resSize = 280) {
		$size = $this->getImageSize();
		$im_width = $size['width'];
		$im_height = $size['height'];
		$diff_width = $resSize - $im_width;
		$diff_height = $resSize - $im_height;
		$ratio = 0;

		if($diff_width > $diff_height) {
			$ratio = $im_width / $resSize;
		} else {
			$ratio = $im_height / $resSize;
		}
		if($ratio > 1) {
			$n_width = ($im_width / $ratio) >> 0;
			$n_height = ($im_height / $ratio) >> 0;
			$im = imagecreatetruecolor($n_width, $n_height);
			imagecopyresampled($im, $this->im, 0, 0, 0, 0, $n_width, $n_height, $im_width, $im_height);
			$im_width = $n_width;
			$im_height = $n_height;
		} else {
			$im = imagecreatetruecolor($im_width, $im_height);
			imagecopy($im, $this->im, 0, 0, 0, 0, $im_width, $im_height);
		}
		imagealphablending($im, false);

		if(!$multiple) {
			$face = $this->detecting($im, $im_width, $im_height);
			if($ratio > 1 && $face) {
				$face['x'] = ($face['x'] * $ratio) >> 0;
				$face['y'] = ($face['y'] * $ratio) >> 0;
				$face['w'] = ($face['w'] * $ratio) >> 0;
			}
			imagedestroy($im);
			return $face;
		}

		$faces = [];
		$limit = is_int($multiple) ? min(50, max(2, $multiple)) : 10;
		while(($face = $this->detecting($im, $im_width, $im_height)) && $limit --) {
			$faces[] = $face;
			$w_ = $face['w'] >> 1;
			$x = $face['x'] - $w_; $y = $face['y'] - $w_; $w = $face['w'] << 1;
			imagefilledrectangle($im, $x, $y, $x + $w, $y + $w, 0xffffff);
		}
		if($ratio > 1) {
			foreach($faces as &$face) {
				$face['x'] = ($face['x'] * $ratio) >> 0;
				$face['y'] = ($face['y'] * $ratio) >> 0;
				$face['w'] = ($face['w'] * $ratio) >> 0;
			}
			unset($face);
		}
		imagedestroy($im);
		return $faces;
	}

	private function detecting($im, $image_width, $image_height) {
		$iis = $this->computeII($im, $image_width, $image_height);
		return $this->doDetectGreedyBigToSmall($iis['ii'], $iis['ii2'], $image_width, $image_height);
	}

	private function computeII($im, $image_width, $image_height) {
		$ii_w = $image_width + 1;
		$ii_h = $image_height + 1;
		$ii = []; $ii2 = [];

		for($y = 1; $y < $ii_h - 1; $y ++) {
			$rowsum = 0; $rowsum2 = 0;
			for($x = 1; $x < $ii_w - 1; $x ++) {
				$pixel = imagecolorat($im, $x, $y);
				$red = ($pixel >> 16) & 0xff;
				$green = ($pixel >> 8) & 0xff;
				$blue = $pixel & 0xff;
				$grey = (.2989 * $red + .587 * $green + .114 * $blue) >> 0;
				$rowsum += $grey;
				$rowsum2 += $grey * $grey;

				$ii_above = ($y - 1) * $ii_w + $x;
				$ii_this = $y * $ii_w + $x;

				$ii[$ii_this] = (isset($ii[$ii_above]) ? $ii[$ii_above] : 0) + $rowsum;
				$ii2[$ii_this] = (isset($ii2[$ii_above]) ? $ii2[$ii_above] : 0) + $rowsum2;
			}
		}
		return ['ii' => $ii, 'ii2' => $ii2];
	}

	private function doDetectGreedyBigToSmall($ii, $ii2, $width, $height) {
		$s_w = $width / 20;
		$s_h = $height / 20;
		$start_scale = $s_h < $s_w ? $s_h : $s_w;
		$scale_update = 1 / 1.2;
		for($scale = $start_scale; $scale > 1; $scale *= $scale_update) {
			$w = (20 * $scale) >> 0;
			$endx = $width - $w - 1;
			$endy = $height - $w - 1;
			$step = max($scale, 2) >> 0;
			$inv_area = 1 / ($w * $w);
			for($y = 0; $y < $endy; $y += $step) {
				for($x = 0; $x < $endx; $x += $step) {
					$passed = $this->detectOnSubImage($x, $y, $scale, $ii, $ii2, $w, $width + 1, $inv_area);
					if($passed) return ['x' => $x, 'y' => $y, 'w' => $w];
				}
			}
		}
		return null;
	}

	private function detectOnSubImage($x, $y, $scale, $ii, $ii2, $w, $iiw, $inv_area) {
		$mean = ((isset($ii[($y + $w) * $iiw + $x + $w]) ? $ii[($y + $w) * $iiw + $x + $w] : 0) + (isset($ii[$y * $iiw + $x]) ? $ii[$y * $iiw + $x] : 0) - (isset($ii[($y + $w) * $iiw + $x]) ? $ii[($y + $w) * $iiw + $x] : 0) - (isset($ii[$y * $iiw + $x + $w]) ? $ii[$y * $iiw + $x + $w] : 0)) * $inv_area;
		$vnorm = ((isset($ii2[($y + $w) * $iiw + $x + $w]) ? $ii2[($y + $w) * $iiw + $x + $w] : 0) + (isset($ii2[$y * $iiw + $x]) ? $ii2[$y * $iiw + $x] : 0) - (isset($ii2[($y + $w) * $iiw + $x]) ? $ii2[($y + $w) * $iiw + $x] : 0) - (isset($ii2[$y * $iiw + $x + $w]) ? $ii2[$y * $iiw + $x + $w] : 0)) * $inv_area - ($mean * $mean);
		$vnorm = $vnorm > 1 ? sqrt($vnorm) : 1;

		$count_data = count(self::$detection);

		for($i_stage = 0; $i_stage < $count_data; $i_stage ++) {
			$stage = self::$detection[$i_stage];
			$trees = $stage[0];
			$stage_thresh = $stage[1];
			$stage_sum = 0;
			$count_trees = count($trees);

			for($i_tree = 0; $i_tree < $count_trees; $i_tree ++) {
				$tree = $trees[$i_tree];
				$current_node = $tree[0];
				$tree_sum = 0;
				while($current_node != null) {
					$vals = $current_node[0];
					$node_thresh = $vals[0];
					$leftval = $vals[1];
					$rightval = $vals[2];
					$leftidx = $vals[3];
					$rightidx = $vals[4];
					$rects = $current_node[1];
					$rect_sum = 0;
					$count_rects = count($rects);

					for($i_rect = 0; $i_rect < $count_rects; $i_rect ++) {
						$s = $scale;
						$rect = $rects[$i_rect];
						$rx = ($rect[0] * $s + $x) >> 0;
						$ry = ($rect[1] * $s + $y) >> 0;
						$rw = ($rect[2] * $s) >> 0;
						$rh = ($rect[3] * $s) >> 0;
						$wt = $rect[4];
						$r_sum = ((isset($ii[($ry + $rh) * $iiw + $rx + $rw]) ? $ii[($ry + $rh) * $iiw + $rx + $rw] : 0) + (isset($ii[$ry * $iiw + $rx]) ? $ii[$ry * $iiw + $rx] : 0) - (isset($ii[($ry + $rh) * $iiw + $rx]) ? $ii[($ry + $rh) * $iiw + $rx] : 0) - (isset($ii[$ry * $iiw + $rx + $rw]) ? $ii[$ry * $iiw + $rx + $rw] : 0)) * $wt;
						$rect_sum += $r_sum;
					}

					$rect_sum *= $inv_area;

					$current_node = null;

					if($rect_sum >= $node_thresh * $vnorm) {
						if($rightidx == -1) {
							$tree_sum = $rightval;
						} else {
							$current_node = $tree[$rightidx];
						}
					} else {
						if($leftidx == -1) {
							$tree_sum = $leftval;
						} else {
							$current_node = $tree[$leftidx];
						}
					}
				}

				$stage_sum += $tree_sum;
			}
			if($stage_sum < $stage_thresh) return false;
		}
		return true;
	}

	public static function AlignDirection($img_width, $img_height, $x, $y, $w) {
		if($img_width == $img_height) return 'center';
		if($img_width > $img_height) {
			$iw = $img_width - $w;
			$ratio = (($x << 1) - $iw) / $iw;
			return $ratio < -.3 ? 'left' : ($ratio > .3 ? 'right' : 'center');
		}
		$ih = $img_height - $w;
		$ratio = (($y << 1) - $ih) / $ih;
		return $ratio < -.3 ? 'top' : ($ratio > .3 ? 'bottom' : 'middle');
	}

	public static function FilterSmallFaces($faces) {
		$maxw = 0;
		foreach($faces as $face) $maxw = max($maxw, $face['w']);
		$maxw *= .4; // 최대크기 대비 60% 이상 작은것 없앰.
		$res = [];
		foreach($faces as $face) {
			if($face['w'] > $maxw) $res[] = $face;
		}
		return $res;
	}
}