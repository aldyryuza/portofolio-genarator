<?php

namespace App\Enums;

enum AnalyticsEventType: string
{
    case PageView = 'page_view';
    case CvDownload = 'cv_download';
}
