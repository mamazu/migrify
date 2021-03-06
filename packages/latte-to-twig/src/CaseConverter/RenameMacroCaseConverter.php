<?php

declare(strict_types=1);

namespace Migrify\LatteToTwig\CaseConverter;

use Migrify\LatteToTwig\Contract\CaseConverter\CaseConverterInterface;
use Nette\Utils\Strings;

final class RenameMacroCaseConverter implements CaseConverterInterface
{
    public function getPriority(): int
    {
        return 300;
    }

    public function convertContent(string $content): string
    {
        return Strings::replace($content, '#{spaceless}(.*?){/spaceless}#ms', '{% spaceless %}$1{% endspaceless %}');
    }
}
