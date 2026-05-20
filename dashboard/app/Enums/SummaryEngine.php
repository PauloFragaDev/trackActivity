<?php

namespace App\Enums;

/**
 * Motor que generó el texto de un generated_summary. Se persiste como
 * string en `generated_summaries.engine` (cast del modelo GeneratedSummary).
 */
enum SummaryEngine: string
{
    case Template = 'template';
    case Llm      = 'llm';
    case Manual   = 'manual';
}
