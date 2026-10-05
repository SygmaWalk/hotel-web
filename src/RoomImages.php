<?php
declare(strict_types=1);
final class RoomImages
{
    public static function directory(): string
    {
        return getenv('HOTEL_UPLOAD_DIR') ?: dirname(__DIR__) . '/storage/rooms';
    }

    public static function validate(array $upload): array
    {
        if (!$upload) return [];
        foreach (['name','tmp_name','error','size'] as $key) {
            if (!isset($upload[$key]) || !is_array($upload[$key])) throw new DomainException('El formato de los archivos no es válido.');
        }
        if (count($upload['error']) > 5) throw new DomainException('Elegí hasta cinco imágenes.');
        $files = [];
        $types = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
        foreach ($upload['error'] as $i=>$error) {
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            if ($error !== UPLOAD_ERR_OK) throw new DomainException('No se pudo cargar una imagen. Cada archivo debe pesar como máximo 1 MB.');
            $path = $upload['tmp_name'][$i] ?? null;
            if (!is_string($path) || !is_uploaded_file($path)) throw new DomainException('Archivo adjunto inválido.');
            $size = filesize($path);
            if (!$size || $size > 1048576) throw new DomainException('Cada imagen debe pesar entre 1 byte y 1 MB.');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
            $dimensions = @getimagesize($path);
            if (!isset($types[$mime]) || !$dimensions || ($dimensions['mime'] ?? '') !== $mime ||
                $dimensions[0] > 6000 || $dimensions[1] > 6000 || $dimensions[0] * $dimensions[1] > 20000000) {
                throw new DomainException('Usá imágenes JPG, PNG o WebP válidas, de hasta 6000 píxeles por lado y 20 megapíxeles.');
            }
            $files[] = ['path'=>$path, 'mime'=>$mime, 'extension'=>$types[$mime]];
        }
        return $files;
    }

    public static function store(array $file): string
    {
        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('No se pudo crear el almacenamiento.');
        $name = bin2hex(random_bytes(24)) . '.' . $file['extension'];
        if (!move_uploaded_file($file['path'], $directory . '/' . $name)) throw new RuntimeException('No se pudo guardar la imagen.');
        return $name;
    }
}
