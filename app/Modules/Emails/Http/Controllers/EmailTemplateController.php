<?php

namespace App\Modules\Emails\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Emails\Mail\OperationalTemplateTestMail;
use App\Modules\Emails\Services\EmailTemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EmailTemplateController extends Controller
{
    public function __construct(
        private readonly EmailTemplateService $emailTemplates,
    ) {}

    public function index(): View
    {
        return view('modules.emails.index', [
            'templates' => $this->emailTemplates->templates()
                ->map(function (array $template): array {
                    $preview = $this->emailTemplates->renderPreview(
                        $template['type'],
                        $template['subject'],
                        $template['html_body'],
                        request()->user(),
                    );

                    return [
                        ...$template,
                        'preview' => $this->withPreviewDocument($preview),
                    ];
                }),
        ]);
    }

    public function update(Request $request, string $type): RedirectResponse
    {
        $this->abortIfUnknownType($type);

        $payload = $this->validatedPayload($request);

        $this->emailTemplates->save(
            $type,
            $payload['subject'],
            $payload['html_body'],
            $request->boolean('is_enabled'),
            $request->user(),
        );

        return redirect()
            ->route('admin.emails.index', ['type' => $type])
            ->with('status', 'Template de e-mail atualizado com sucesso.');
    }

    public function restore(string $type): RedirectResponse
    {
        $this->abortIfUnknownType($type);

        $this->emailTemplates->restore($type);

        return redirect()
            ->route('admin.emails.index', ['type' => $type])
            ->with('status', 'Template padrao restaurado.');
    }

    public function preview(Request $request, string $type): JsonResponse
    {
        $this->abortIfUnknownType($type);

        $payload = $this->validatedPayload($request);
        $rendered = $this->emailTemplates->renderPreview(
            $type,
            $payload['subject'],
            $payload['html_body'],
            $request->user(),
        );

        return response()->json($this->withPreviewDocument($rendered));
    }

    public function sendTest(Request $request, string $type): JsonResponse
    {
        $this->abortIfUnknownType($type);

        $payload = $this->validatedPayload($request);
        $rendered = $this->emailTemplates->renderPreview(
            $type,
            $payload['subject'],
            $payload['html_body'],
            $request->user(),
        );

        try {
            Mail::to($request->user()->email)->send(
                new OperationalTemplateTestMail($rendered['subject'], $rendered['html']),
            );
        } catch (Throwable $throwable) {
            Log::warning('Operational email test delivery failed.', [
                'type' => $type,
                'user_id' => $request->user()->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return response()->json([
                'message' => 'Nao foi possivel enviar o teste com o mailer atual.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'E-mail de teste enviado para '.$request->user()->email.'.',
        ]);
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'html_body' => ['required', 'string', 'max:30000'],
        ]);
    }

    private function abortIfUnknownType(string $type): void
    {
        abort_unless($this->emailTemplates->exists($type), Response::HTTP_NOT_FOUND);
    }

    private function withPreviewDocument(array $rendered): array
    {
        return [
            ...$rendered,
            'document' => view('emails.operational-template', [
                'html' => $rendered['html'],
                'subject' => $rendered['subject'],
            ])->render(),
        ];
    }
}
