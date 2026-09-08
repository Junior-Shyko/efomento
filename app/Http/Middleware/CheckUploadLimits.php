<?php

namespace App\Http\Middleware;

use App\Exceptions\Domain\FileUploadExceededException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class CheckUploadLimits
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->checkUploadedFiles($request->allFiles());

        return $next($request);
    }

    private function checkUploadedFiles(array $files): void
    {
        foreach ($files as $file) {
            if (is_array($file)) {
                $this->checkUploadedFiles($file);

                continue;
            }

            if ($file instanceof UploadedFile) {
                $error = $file->getError();
                if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                    throw FileUploadExceededException::fromIniLimits();
                }
            }
        }
    }
}
