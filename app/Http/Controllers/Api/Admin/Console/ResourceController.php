<?php

namespace App\Http\Controllers\Api\Admin\Console;

use App\Admin\Console\Action;
use App\Admin\Console\AdminResource;
use App\Admin\Console\ResourceRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Le moteur de console : un contrôleur, tous les domaines. Il ne sait rien d'aucun métier. */
class ResourceController extends Controller
{
    /** Au-delà, une page cesse d'être une page. */
    private const MAX_PER_PAGE = 100;

    private const DEFAULT_PER_PAGE = 25;

    public function __construct(private readonly ResourceRegistry $registry) {}

    /**
     * Un refus, sous les DEUX conventions de la plateforme.
     *
     * @param  array<string, mixed>  $extra
     */
    private function refus(string $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => $code,
            'error_code' => $code,
        ] + $extra, $status);
    }

    /** Un compte en LECTURE SEULE n'écrit rien, par aucune des quatre portes. */
    private function refuseLecteurSeul(): ?JsonResponse
    {
        $user = request()->user();

        if ($user instanceof User && $user->isReadOnlyAdmin()) {
            return $this->refus('forbidden_readonly', 403);
        }

        return null;
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        $descripteur = $this->resolve($resource);

        if (! $descripteur instanceof AdminResource) {
            return $this->unknownResource();
        }

        $sort = (string) $request->query('sort', $descripteur->defaultSort());

        if (! in_array($sort, $descripteur->sorts(), true)) {
            return $this->refus('invalid_sort', 422, ['allowed' => $descripteur->sorts()]);
        }

        $direction = strtolower((string) $request->query('direction', 'desc'));

        if (! in_array($direction, ['asc', 'desc'], true)) {
            return $this->refus('invalid_direction', 422);
        }

        $query = $descripteur->query();

        /** @var array<string, mixed> $filtres */
        $filtres = (array) $request->query('filters', []);
        $declares = array_map(fn ($f) => $f->key(), $descripteur->filters());

        foreach ($filtres as $key => $value) {
            if (! in_array($key, $declares, true) || $value === '' || $value === null) {
                continue;
            }

            $query = $descripteur->applyFilter($query, (string) $key, $value);
        }

        $perPage = min(
            max((int) $request->query('per_page', self::DEFAULT_PER_PAGE), 1),
            self::MAX_PER_PAGE,
        );

        // `orderBy` explicite puis `cursorPaginate` : le curseur se construit sur la colonne de tri, donc un tri instable produirait des pages qui se chevauchent.
        $query = $query->orderBy($sort, $direction);

        if ($sort !== 'id') {
            $query = $query->orderBy('id', $direction);
        }

        $page = $query->cursorPaginate($perPage);

        return response()->json([
            'ok' => true,
            'resource' => $this->describe($descripteur),
            'rows' => array_map(
                fn (Model $model) => $descripteur->toRow($model),
                $page->items(),
            ),
            'next_cursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function show(string $resource, string $id): JsonResponse
    {
        $descripteur = $this->resolve($resource);

        if (! $descripteur instanceof AdminResource) {
            return $this->unknownResource();
        }

        $model = $descripteur->query()->find($id);

        if (! $model instanceof Model) {
            return $this->refus('not_found', 404);
        }

        return response()->json(['ok' => true, 'row' => $descripteur->toDetail($model)]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        if ($refus = $this->refuseLecteurSeul()) {
            return $refus;
        }

        $descripteur = $this->resolve($resource);

        if (! $descripteur instanceof AdminResource) {
            return $this->unknownResource();
        }

        if ($descripteur->formFields() === []) {
            return $this->refus('read_only_resource', 405);
        }

        $data = $this->validated($request, $descripteur);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $model = $descripteur->query()->getModel()->newInstance();

        // Le descripteur complète ce que le formulaire n'a pas à demander : un slug déduit, un
        // état initial décidé par la plateforme.
        $model->forceFill($descripteur->prepareForCreate($data))->save();

        return response()->json(['ok' => true, 'row' => $descripteur->toDetail($model)], 201);
    }

    public function update(Request $request, string $resource, string $id): JsonResponse
    {
        if ($refus = $this->refuseLecteurSeul()) {
            return $refus;
        }

        $descripteur = $this->resolve($resource);

        if (! $descripteur instanceof AdminResource) {
            return $this->unknownResource();
        }

        if ($descripteur->formFields() === []) {
            return $this->refus('read_only_resource', 405);
        }

        $model = $descripteur->query()->find($id);

        if (! $model instanceof Model) {
            return $this->refus('not_found', 404);
        }

        // Édition partielle : seules les règles des champs REÇUS s'appliquent. Valider tout le
        // formulaire obligerait à renvoyer des champs qu'on ne modifie pas.
        $data = $this->validated($request, $descripteur, partial: true);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $model->forceFill($data)->save();

        return response()->json(['ok' => true, 'row' => $descripteur->toDetail($model)]);
    }

    public function destroy(string $resource, string $id): JsonResponse
    {
        if ($refus = $this->refuseLecteurSeul()) {
            return $refus;
        }

        $descripteur = $this->resolve($resource);

        if (! $descripteur instanceof AdminResource) {
            return $this->unknownResource();
        }

        $model = $descripteur->query()->find($id);

        if (! $model instanceof Model) {
            return $this->refus('not_found', 404);
        }

        // On DEMANDE au descripteur avant de détruire.
        $raisons = $descripteur->reasonsToRefuseDelete($model);

        if ($raisons !== []) {
            return response()->json([
                'ok' => false,
                'error' => 'delete_refused',
                'error_code' => 'delete_refused',
                'reasons' => $raisons,
            ], 409);
        }

        $model->delete();

        return response()->json(['ok' => true]);
    }

    /** Une action qui ne porte sur aucune ligne. */
    public function globalAction(Request $request, string $resource, string $action): JsonResponse
    {
        if ($refus = $this->refuseLecteurSeul()) {
            return $refus;
        }

        $descripteur = $this->resolve($resource);

        if (! $descripteur instanceof AdminResource) {
            return $this->unknownResource();
        }

        $declaree = null;

        foreach ($descripteur->globalActions() as $candidate) {
            if ($candidate->key() === $action) {
                $declaree = $candidate;
                break;
            }
        }

        if (! $declaree instanceof Action) {
            return $this->refus('unknown_action', 404);
        }

        $saisie = [];

        if ($declaree->fields() !== []) {
            $rules = [];

            foreach ($declaree->fields() as $field) {
                $rules[$field->key()] = $field->validationRules();
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return $this->refus('validation_failed', 422, ['errors' => $validator->errors()->toArray()]);
            }

            $saisie = array_intersect_key($validator->validated(), $rules);
        }

        $result = ($declaree->handler())($saisie);

        return response()->json(['ok' => true, 'result' => $result]);
    }

    public function action(Request $request, string $resource, string $id, string $action): JsonResponse
    {
        if ($refus = $this->refuseLecteurSeul()) {
            return $refus;
        }

        $descripteur = $this->resolve($resource);

        if (! $descripteur instanceof AdminResource) {
            return $this->unknownResource();
        }

        $declaree = null;

        foreach ($descripteur->actions() as $candidate) {
            if ($candidate->key() === $action) {
                $declaree = $candidate;
                break;
            }
        }

        if (! $declaree instanceof Action) {
            return $this->refus('unknown_action', 404);
        }

        $model = $descripteur->query()->find($id);

        if (! $model instanceof Model) {
            return $this->refus('not_found', 404);
        }

        // Les valeurs exigées par l'action sont validées ICI, avec ses propres règles.
        $saisie = [];

        if ($declaree->fields() !== []) {
            $rules = [];

            foreach ($declaree->fields() as $field) {
                $rules[$field->key()] = $field->validationRules();
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return $this->refus('validation_failed', 422, [
                    'errors' => $validator->errors()->toArray(),
                ]);
            }

            $saisie = array_intersect_key($validator->validated(), $rules);
        }

        // La confirmation d'une action destructive est une affaire d'INTERFACE.
        $result = ($declaree->handler())($model, $saisie);

        return response()->json(['ok' => true, 'result' => $result]);
    }

    // ── Interne ─────────────────────────────────────────────────────────────────────────────

    /** @return AdminResource<Model>|null */
    private function resolve(string $resource): ?AdminResource
    {
        return $this->registry->for($resource);
    }

    private function unknownResource(): JsonResponse
    {
        return $this->refus('unknown_resource', 404);
    }

    /**
     * @param  AdminResource<Model>  $descripteur
     * @return array<string, mixed>
     */
    private function describe(AdminResource $descripteur): array
    {
        return [
            'key' => $descripteur->key(),
            'columns' => array_map(fn ($c) => $c->toArray(), $descripteur->columns()),
            'filters' => array_map(fn ($f) => $f->toArray(), $descripteur->filters()),
            // Ce que le mobile ne voit pas ici, il ne peut pas le dessiner.
            'global_actions' => array_map(fn ($a) => $a->toArray(), $descripteur->globalActions()),
            'sorts' => $descripteur->sorts(),
            'default_sort' => $descripteur->defaultSort(),
            // `toArray()` d'une action ne porte PAS sa closure : le mobile reçoit une clé.
            'actions' => array_map(fn ($a) => $a->toArray(), $descripteur->actions()),
            'form' => array_map(fn ($f) => $f->toArray(), $descripteur->formFields()),
        ];
    }

    /**
     * Valide la requête contre les règles du descripteur et ne rend QUE les champs déclarés.
     *
     * @param  AdminResource<Model>  $descripteur
     * @return array<string, mixed>|JsonResponse
     */
    private function validated(Request $request, AdminResource $descripteur, bool $partial = false): array|JsonResponse
    {
        $rules = [];

        foreach ($descripteur->formFields() as $field) {
            if ($partial && ! $request->has($field->key())) {
                continue;
            }

            $rules[$field->key()] = $field->validationRules();
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->refus('validation_failed', 422, ['errors' => $validator->errors()->toArray()]);
        }

        // `only()` sur les clés déclarées : la validation ne suffit pas à filtrer, un champ non
        // validé traverserait `$request->all()` jusqu'à l'écriture.
        return array_intersect_key(
            $validator->validated(),
            array_flip(array_keys($rules)),
        );
    }
}
