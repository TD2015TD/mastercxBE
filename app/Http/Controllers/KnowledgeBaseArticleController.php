<?php

namespace App\Http\Controllers;

use App\Services\KnowledgeBaseArticleService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class KnowledgeBaseArticleController extends Controller
{
    protected $service;

    public function __construct(KnowledgeBaseArticleService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string',
            'content' => 'nullable|string',
            'category_code' => 'required|string',
            'author' => 'nullable|string',
            'published_at' => 'nullable|date',
            'file' => 'nullable|file|mimes:pdf,doc,docx',
        ]);

        // If file is uploaded, parse it
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());
            $content = '';
            if ($ext === 'pdf') {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($file->getPathname());
                $content = $pdf->getText();
            } elseif ($ext === 'doc' || $ext === 'docx') {
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($file->getPathname());
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, 'getText')) {
                            $content .= $element->getText() . "\n";
                        }
                    }
                }
            }
            $data['content'] = $content;
        }

        // If no file, require content
        if (empty($data['content'])) {
            return response()->json(['error' => 'Content is required if no file is uploaded.'], 422);
        }

        $article = $this->service->store($data);
        return response()->json($article, 201);
    }

    public function delete($id)
    {
        $this->service->delete($id);
        return response()->json(['message' => 'Deleted'], 200);
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        $category = $request->input('category_code');
        $results = $this->service->search($query, $category);
        return response()->json($results);
    }

    public function aggregateByCategory()
    {
        $agg = $this->service->aggregateByCategory();
        return response()->json($agg);
    }
}
