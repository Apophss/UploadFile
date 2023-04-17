<?php

namespace Apophss\UploadFile\Core;

use Apophss\UploadFile\Exception\UploaderException;

class Upload
{
    protected string $directory;
    protected bool $createDir;
    protected int $maxFilesize ;
    protected string $slug;

    protected int $fileLimit;

    protected string $fileToDelete;
    protected string $deletedFile;
    protected string $trashFolder;

    protected string $name;
    protected string $extension;
    protected string $type;
    protected array $allowedTypes;

    function __construct(
        string $directory,
        bool $createDir = false,
        int $maxFilesize = 10485760,
        string $slug = '_',
    ) {
        $this->directory = $directory;
        $this->createDir = $createDir;
        $this->validate_directory();

        $this->maxFilesize = $maxFilesize ;
        $this->slug = $slug;
        $this->fileLimit = ini_get('max_file_uploads');
    }

    public function fileLimit(int $fileLimit): void
    {
        $this->fileLimit = $fileLimit;
    }

    protected function error_messages(string $error): string
    {
        $errors = array(
            UPLOAD_ERR_OK => "Não há erro, o arquivo foi carregado com sucesso.",
            UPLOAD_ERR_INI_SIZE => "O arquivo enviado excede o limite definido no servidor",
            UPLOAD_ERR_FORM_SIZE => 'O arquivo ultrapassa o limite definido (' . $this->convert_bytes($this->maxFilesize ) . ').',
            UPLOAD_ERR_PARTIAL => "O upload do arquivo foi feito parcialmente.",
            UPLOAD_ERR_NO_FILE => "Nenhum arquivo enviado.",
            UPLOAD_ERR_NO_TMP_DIR => "Pasta temporária ausente.",
            UPLOAD_ERR_CANT_WRITE => "Falha em escrever o arquivo em disco.",
            UPLOAD_ERR_EXTENSION => "Uma extensão do PHP interrompeu o upload do arquivo.",

            'createDir_ERR' => 'O diretório escolhido não existe e a tentativa de criá-lo falhou.',
            'FOLDER_DIR_ERR' => 'O diretório escolhido não existe, experimente habilitar a criação de diretórios.',
            'INVALID_TYPE' => 'Tipo de arquivo ou extensão inválida.',
            'MULTIPLE_UPLOAD_LIMIT' => 'O número de arquivos excedeu o limite definido.',
            'SAVE_FAILED' => 'Erro ao salvar o arquivo.',
        );

        return $errors[$error] ?? 'Erro desconhecido.';
    }

    protected function validate_directory(): void
    {
        if (is_dir($this->directory))
            return;

        if ($this->createDir === true)
            $this->directory = $this->createDirectory(
                $this->format_characters($this->directory, '-')
            );


        if (!is_dir($this->directory))
            throw new UploaderException($this->error_messages('FOLDER_DIR_ERR'));
    }

    protected function set_name(string $file, $name): void
    {
        if ($name == null)
            $newName = $this->format_characters(pathinfo($file, PATHINFO_FILENAME), $this->slug);

        if (is_numeric($name))
            $newName = $this->generate_random_id(intval($name));

        if (is_string($name)) {
            $remove_directory_separator = fn ($str) => mb_strtolower(str_replace(['\\', '/'], $this->slug, $str));
            $newName = $this->format_characters($name, $this->slug, $remove_directory_separator);
        }
        $this->name = "{$newName}.{$this->extension}";
    }

    protected function search_file(): void
    {
        $file = "{$this->directory}/{$this->name}";
        if ((file_exists($file)))
            $this->name = (pathinfo($this->name, PATHINFO_FILENAME) . $this->slug . $this->generate_random_id(10) . '.' . $this->extension);
    }

    protected function enumerate_file(): void
    {
        $file = "{$this->directory}/{$this->name}";
        if ((file_exists($file)) && (is_file($file))) {
            $fileNum = 2;
            $filename = pathinfo($this->name, PATHINFO_FILENAME);

            foreach (glob($this->directory . '/' . $filename . '*') as $fileInDirectory) {
                $fileInDirectory = pathinfo($fileInDirectory);
                $newName =  $filename . $this->slug . $fileNum . '.' . $this->extension;

                if ($fileInDirectory['basename'] == $newName) {
                    if ((mb_strtolower($fileInDirectory['extension'])) === ($this->extension))
                        $fileNum++;
                }
            }

            $this->name = ($filename . $this->slug . $fileNum . '.' . $this->extension);
        }
    }

    // Auxiliary Functions
    private function createDirectory(string $dir): string
    {
        if (!is_dir($dir))
            if (!mkdir($dir, 0777, true))
                throw new UploaderException($this->error_messages('createDir_ERR'));

        return $dir;
    }

    private function convert_bytes(int $bytes, int $precision = 2): string
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function format_characters(string $text, string $slug, $function = 'mb_strtolower'): string
    {

        $search = array('à', 'á', 'â', 'ã', 'ä', 'å', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ü', 'ú', 'ÿ', 'À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ü', 'Ú', '__', '--', '_-', '-_');
        $replace = array('a', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'y', 'A', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'N', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', '_', '-', '-', '-');

        if ($function !== null && is_callable($function)) {
            $text = $function($text);
        }

        $text = preg_replace('/([^\p{L&}\p{Nd}\\\\\/\-\_]+)/u', $slug, $text);
        $text = str_replace($search, $replace, $text);

        return trim($text, " _-\t\n\r\0\x0B");
    }

    private function generate_random_id(int $length = 15): string
    {
        $bytes = random_bytes(ceil($length / 2));
        return substr(bin2hex($bytes), 0, $length);
    }

    // Function for handling old files
    public function delete_file(string $filename, string $trashFolder = '_trash'): void
    {
        $file = "{$this->directory}/{$filename}";
        if ((file_exists($file)) && (is_file($file))) {
            $this->fileToDelete = $file;
            $this->trashFolder = $this->createDirectory("{$this->directory}/{$trashFolder}");
            $this->delete_file_action();
        }
    }

    protected function delete_file_action(): void
    {
        if (!empty($this->fileToDelete)) {
            $fileInfo = pathinfo($this->fileToDelete);
            $hash = (new \DateTime('now'))->format('dmy-His-u');
            $trashFilename = $fileInfo['filename'] . $this->slug . $hash . '.' . $fileInfo['extension'];

            if (rename(
                $this->fileToDelete,
                $this->trashFolder . '/' . $trashFilename
            ))
                $this->deletedFile = $this->trashFolder . '/' . $trashFilename;
        }
    }

    protected function undelete_file(): void
    {
        if ((!empty($this->deletedFile)) && (!empty($this->fileToDelete))) {
            rename(
                $this->deletedFile,
                $this->fileToDelete
            );
        }
    }
}
