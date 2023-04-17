<?php

namespace Apophss\UploadFile;

use Apophss\UploadFile\Core\Upload;
use Apophss\UploadFile\Exception\UploaderException;

class File extends Upload
{

    protected array $allowedTypes = [
        "zip" => "application/zip",
        "rar" => 'application/x-rar-compressed',
        "bz" => 'application/x-bzip',
        "pdf" => "application/pdf",
        "doc" => "application/msword",
        "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "csv" => "text/csv",
        "xls" => "application/vnd.ms-excel",
        "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "ods" => "application/vnd.oasis.opendocument.spreadsheet",
        "odt" => "application/vnd.oasis.opendocument.text",
    ];

    public function upload(array $file, string $name): array
    {
        try {
            if ($file['error'] != UPLOAD_ERR_OK)
                throw new UploaderException($this->error_messages($file['error']));

            if ((filesize($file['tmp_name'])) > $this->maxFilesize)
                throw new UploaderException($this->error_messages(UPLOAD_ERR_FORM_SIZE));

            $this->set_file_info($file['tmp_name']);

            $this->set_name($file['name'], $name);

            $this->search_file();

            if (!move_uploaded_file($file['tmp_name'], "{$this->directory}/{$this->name}"))
                throw new UploaderException($this->error_messages('SAVE_FAILED'));
        } catch (UploaderException $e) {
            $this->undelete_file();

            return array(
                'error' => $e->getMessage(),
                'file_error' => $file['name'],
                'file_type' => $this->type ?? '',
                'file_extension' => $this->extension ?? '',
            );
        }

        return array(
            'file' => $this->name,
            'file_dir' => "{$this->directory}/{$this->name}"
        );
    }
    public function multiple_upload(array $file, string $name): array
    {
        for ($i = 0; $i < count($file['tmp_name']); $i++) {
            try {
                if ($i > $this->fileLimit)
                    throw new UploaderException($this->error_messages('MULTIPLE_UPLOAD_LIMIT'));

                if ($file['error'][$i] != UPLOAD_ERR_OK)
                    throw new UploaderException($this->error_messages($file['error']));

                if ((filesize($file['tmp_name'][$i])) > $this->maxFilesize)
                    throw new UploaderException($this->error_messages(UPLOAD_ERR_FORM_SIZE));

                $this->set_file_info($file['tmp_name'][$i]);

                $this->set_name($file['name'][$i], $name);

                $this->enumerate_file();

                if (!move_uploaded_file($file['tmp_name'][$i], "{$this->directory}/{$this->name}"))
                    throw new UploaderException($this->error_messages('SAVE_FAILED'));
            } catch (UploaderException $e) {
                $uploadedFiles[] = array(
                    'error' => $e->getMessage(),
                    'file_error' => $file['name'][$i],
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

    protected function set_file_info(string $file): void
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $this->type = $finfo->file($file);

        if (!in_array($this->type, array_values($this->allowedTypes)))
            throw new UploaderException($this->error_messages('INVALID_TYPE'));

        $this->extension = array_keys($this->allowedTypes, $this->type)[0];
    }
}
