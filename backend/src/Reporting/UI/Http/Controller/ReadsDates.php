<?php

namespace App\Reporting\UI\Http\Controller;

use App\Shared\UI\Http\ApiException;
use Symfony\Component\HttpFoundation\Request;

/** Query-string dates of the reports: YYYY-MM-DD, else 400 invalid_date. */
trait ReadsDates
{
    private static function date(Request $request, string $name): ?\DateTimeImmutable
    {
        $value = $request->query->getString($name);
        if ('' === $value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw ApiException::badRequest('invalid_date', \sprintf('"%s" must be a date as YYYY-MM-DD.', $name));
        }

        return $date;
    }
}
