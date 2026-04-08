<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;
use SPC\util\CustomExt;

#[CustomExt('xlswriter')]
class xlswriter extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        $arg = '--with-xlswriter --enable-reader';
        if ($this->builder->getLib('openssl')) {
            $arg .= ' --with-openssl=' . BUILD_ROOT_PATH;
        }
        return $arg;
    }

    public function getWindowsConfigureArg(bool $shared = false): string
    {
        return '--with-xlswriter';
    }

    public function patchBeforeMake(): bool
    {
        $patched = parent::patchBeforeMake();

        // Fix K&R C function declaration in bundled minizip rejected by modern Clang (C23 default)
        $mztools = $this->source_dir . '/library/libxlsxwriter/third_party/minizip/mztools.c';
        if (file_exists($mztools)) {
            FileSystem::replaceFileStr(
                $mztools,
                "extern int ZEXPORT unzRepair(file, fileOut, fileOutTmp, nRecovered, bytesRecovered)\nconst char* file;\nconst char* fileOut;\nconst char* fileOutTmp;\nuLong* nRecovered;\nuLong* bytesRecovered;\n{",
                "extern int ZEXPORT unzRepair(const char* file, const char* fileOut, const char* fileOutTmp, uLong* nRecovered, uLong* bytesRecovered)\n{"
            );
            $patched = true;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            // fix windows build with openssl extension duplicate symbol bug
            SourcePatcher::patchFile('spc_fix_xlswriter_win32.patch', $this->source_dir);
            $content = file_get_contents($this->source_dir . '/library/libxlsxwriter/src/theme.c');
            $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
            if (!str_starts_with($content, $bom)) {
                file_put_contents($this->source_dir . '/library/libxlsxwriter/src/theme.c', $bom . $content);
            }
            return true;
        }
        return $patched;
    }
}
