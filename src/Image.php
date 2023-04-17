<?php

namespace Apophss\UploadFile;

use Apophss\UploadFile\Core\Upload;
use Apophss\UploadFile\Exception\UploaderException;

class Image extends Upload
{
    protected $imageCreated;

    protected array $allowedTypes = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
    ];

    public function upload(array $image, $name = null, int $width = null, array $quality = ['jpg' => 75, 'png' => 5]): array
    {
        try {
            if ($image['error'] != UPLOAD_ERR_OK)
                throw new UploaderException($this->error_messages($image['error']));

            if ((filesize($image['tmp_name'])) > $this->maxFilesize)
                throw new UploaderException($this->error_messages(UPLOAD_ERR_FORM_SIZE));

            $this->set_image_info($image['tmp_name']);

            $this->set_name($image['name'], $name);

            $this->search_file();

            if (!$this->save_image($image['tmp_name'], $width, $quality))
                throw new UploaderException($this->error_messages('SAVE_FAILED'));
        } catch (UploaderException $e) {
            $this->undelete_file();

            return array(
                'error' => $e->getMessage(),
                'error_file' => $image['name'],
                'file_type' => $this->type ?? '',
                'file_extension' => $this->extension ?? '',
            );
        }

        return array(
            'file' => $this->name,
            'file_dir' => "{$this->directory}/{$this->name}"
        );
    }

    public function multiple_upload(array $image, $name = null, int $width = null, array $quality = ['jpg' => 75, 'png' => 5]): array
    {
        for ($i = 0; $i < count($image['tmp_name']); $i++) {
            try {
                if ($i > $this->fileLimit)
                    throw new UploaderException($this->error_messages('MULTIPLE_UPLOAD_LIMIT'));

                if ($image['error'][$i] != UPLOAD_ERR_OK)
                    throw new UploaderException($this->error_messages($image['error']));

                if ((filesize($image['tmp_name'][$i])) > $this->maxFilesize)
                    throw new UploaderException($this->error_messages(UPLOAD_ERR_FORM_SIZE));

                $this->set_image_info($image['tmp_name'][$i]);

                $this->set_name($image['name'][$i], $name);

                $this->enumerate_file();

                if (!$this->save_image($image['tmp_name'][$i], $width, $quality))
                    throw new UploaderException($this->error_messages('SAVE_FAILED'));
            } catch (UploaderException $e) {
                $uploadedFiles[] = array(
                    'error' => $e->getMessage(),
                    'file_error' => $image['name'][$i],
                    'file_type' => $this->type ?? '',
                    'file_extension' => $this->extension ?? '',
                );
                continue;
            }

            $uploadedFiles[] = array(
                'file' => $this->name,
                'file_dir' => "{$this->directory}/{$this->name}"
            );
        };

        return $uploadedFiles;
    }


    protected function set_image_info(string $image): void
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $this->type = $finfo->file($image);

        switch ($this->type) {
            case 'image/jpeg':
                $this->imageCreated = imagecreatefromjpeg($image);
                $this->extension = array_keys($this->allowedTypes, $this->type)[0];
                break;

            case 'image/png':
                $this->imageCreated = imagecreatefrompng($image);
                $this->extension = array_keys($this->allowedTypes, $this->type)[0];
                break;

            case 'image/gif':
                $this->imageCreated = '';
                $this->extension = array_keys($this->allowedTypes, $this->type)[0];
                break;

            default:
                throw new UploaderException($this->error_messages('INVALID_TYPE'));
        }
    }

    private function save_image(string $image, ?int $width, array $quality): bool
    {
        if (empty($this->imageCreated)) {
            if ($this->extension == 'gif') {
                return move_uploaded_file($image, "{$this->directory}/{$this->name}");
            }
        } else {
            $fileX = intval(imagesx($this->imageCreated));
            $fileY = intval(imagesy($this->imageCreated));
            $imageW = intval((($width != null) && ($width < $fileX)) ? $width : $fileX);
            $imageH = intval(($imageW * $fileY) / $fileX);
            $imageCreate = imagecreatetruecolor($imageW, $imageH);

            if ($this->extension == 'jpg') {
                imagecopyresampled($imageCreate, $this->imageCreated, 0, 0, 0, 0, $imageW, $imageH, $fileX, $fileY);
                return imagejpeg($imageCreate, "{$this->directory}/{$this->name}", $quality['jpg']);
            }

            if ($this->extension == 'png') {
                imagealphablending($imageCreate, false);
                imagesavealpha($imageCreate, true);
                imagecopyresampled($imageCreate, $this->imageCreated, 0, 0, 0, 0, $imageW, $imageH, $fileX, $fileY);
                return imagepng($imageCreate, "{$this->directory}/{$this->name}", $quality['png']);
            }

            imagedestroy($this->imageCreated);
            imagedestroy($imageCreate);
        }
    }
}
