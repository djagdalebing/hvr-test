<?php

namespace Common\Csv;

use Auth;
use Carbon\Carbon;
use Common\Core\BaseController;
use Illuminate\Http\Request;
use Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BaseCsvExportController extends BaseController
{
    /**
     * @var Request
     */
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;

        $this->middleware('auth');
    }

    public function download(CsvExport $csvExport): StreamedResponse
    {
        if ($csvExport->user_id !== Auth::id()) {
            abort(403);
        }

        return Storage::download($csvExport->filePath(), $csvExport->download_name);
    }

    protected function exportUsing(BaseCsvExportJob $exportJob)
    {
        // Generate the CSV synchronously and hand back an immediate download
        // link. No queue, no email — the admin clicks "Export to CSV" and the
        // browser downloads the file straight away. (The old flow dispatched a
        // job that emailed a link, which 500'd whenever mail was misconfigured.)
        $csvExport = $exportJob->generateAndStore();
        return $this->success([
            'downloadPath' => $csvExport->downloadLink(),
        ]);
    }
}
