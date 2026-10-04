<?php

namespace App\Reporting\Application\Export;

/** What a column holds: decides how the PDF prints it (the CSV always writes the raw, machine-readable value). */
enum ColumnKind: string
{
    case Text = 'text';
    /** A decimal string: "1190000.00" in the CSV, "$ 1.190.000,00" on the PDF. */
    case Money = 'money';
    /** A date as Y-m-d: DD/MM/YYYY on the PDF. */
    case Date = 'date';
    case Number = 'number';

    public function isNumeric(): bool
    {
        return self::Money === $this || self::Number === $this;
    }
}
