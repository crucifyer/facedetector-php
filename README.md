# Face Detector PHP

GD 를 이용해 이미지의 얼굴 위치를 식별합니다.

- [mauricesvay/php-facedetection](https://github.com/mauricesvay/php-facedetection) 프로젝트를 기반으로 만들었습니다.
- [js 로 포팅](https://github.com/crucifyer/facedetector-js)하며 개선한 사항들을 적용하여 error_reporting = E_ALL safe 하게 php 코드를 새로 작성했습니다.
- 라이센스가 GPL-2.0 인 이유는 [원본의 라이센스](https://github.com/mauricesvay/php-facedetection/issues/18)를 유지해야하기 때문입니다.
- 이미지 크기에 따라 인식률에 큰 차이가 생깁니다. 알고리즘의 원리를 이해하지 못해서 크기 외의 인식률 개선 작업을 하지 못했습니다. 테스트에 사용된 이미지들은 대부분 281px 일 때의 결과가 가장 좋기 때문에 기본값이 281 입니다.
- 자원소모가 심한편이기 때문에 썸네일 정렬 용도로 사용하려면, 실시간 적용 보다는 업로드시 검출하여 direction 값을 함께 저장하는것이 좋습니다.

```bash
$ php composer.phar require "crucifyer/facedetector-php" "dev-main"
```

```php
$detector = new \Xeno\Image\FaceDetector(imagefile or gdresource or imagebinary);
$face = $detector->FaceDetect();
['x' => int, 'y' => int, 'w' => int]

$faces = $detector->FaceDetect(true);
[
	['x' => int, 'y' => int, 'w' => int],
	['x' => int, 'y' => int, 'w' => int],
	['x' => int, 'y' => int, 'w' => int],
	['x' => int, 'y' => int, 'w' => int],
	['x' => int, 'y' => int, 'w' => int],
]

FaceDetector::FaceDetect([multiplue bool], [resSize int])
```
- gif, jpeg, png 이미지 파일, gd 리소스, 이미지 바이너리가 가능합니다.
#### multiple 
- multiple 값을 true 로 주면 얼굴위치를 최대 10개까지 더 찾아서 배열로 반환합니다.
- true 대신 숫자를 입력할 수 있고, 2~50으로 제한됩니다.
- 여러 얼굴 인식률이 특히 안좋아서 이미지 크기를 바꿔도 모든 얼굴을 인식하지 못할 확률이 높습니다.
#### resSize
- 원본 이미지를 그대로 사용하지 않고 이미지 크기를 줄여서 사용합니다. 가로,세로 중 작은쪽 기준으로 줄이며, 기본값은 280 입니다.

```php
$face = $detector->FaceDetect();
$size = $detector->getImageSize();
$direction = \Xeno\Image\FaceDetector::AlignDirection($size['width'], $size['height'], $face['x'], $face['y'], $face['w']);
```
- 얼굴쪽으로 정렬을 하기 위한 방향을 반환합니다.
- 가로가 길면 left, center, right, 세로가 길면 top, middle, bottom 중 얼굴이 감지된 위치를 반환합니다.
- 약 30% 이상 치우쳤는지로 판단합니다.

```php
$faces = \Xeno\Image\FaceDetector::FilterSmallFaces($faces);
```
- 가장 큰 것 대비 60% 이상 작은것을 걸러냅니다.

```php
$detector = new \Xeno\Image\FaceDetector(imagefile or gdresource or imagebinary);
$direction = $detector->getDirection();
```
- 방향 인식까지의 과정을 자동화 합니다. 여러 얼굴을 인식하면 중앙정렬 합니다.

```php
$detector = new \Xeno\Image\FaceDetector(imagefile or gdresource or imagebinary);
[gd resource or boolean] = $detector->cropThumbnail(size, [direction], [file], [type]);
```
- 크롭된 썸네일을 반환합니다. size 보다 원본이 작아도 키워서 맞추지 않고, 비율대로 자르기만 합니다.
#### direction
- 생략하면 getDirection 을 해서 채웁니다.
#### file
- 생략하면 gd resource 를 반환합니다.
#### type
- gif, jpg, png, png8 을 지원하고 생략하면 file 의 확장자로 판별합니다.
- png8 은 gif 와 같은 방식인 indexed colors 라서 용량이 작고 화질이 안좋습니다.

### 전반적인 예제가 tests/example.php 에 들어있습니다. autoload.php 위치를 수정하고 콘솔에서 테스트하세요.

### js project: [https://github.com/crucifyer/facedetector-js](https://github.com/crucifyer/facedetector-js)