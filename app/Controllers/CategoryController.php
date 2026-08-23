<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Response;
use App\Models\CategoryRepository;
use App\Services\ActivityLog;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Kategori uçları — docs/10 §7.
 *
 * Kategori serbest metin değildir: ürünler tanımlı listeden `category_id` ile bağlanır
 * (docs/04 §2d). Bu yüzden kullanımda olan kategori silinemez — silinseydi ürünler
 * kategorisiz kalır ve filtreler sessizce bozulurdu.
 */
final class CategoryController extends ApiController
{
    private const MAX_NAME_LENGTH = 100;

    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly ActivityLog $activity,
        private readonly Clock $clock,
    ) {
    }

    /** GET /api/categories */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Response::success($response, $this->categories->all());
    }

    /**
     * POST /api/categories/import — TOPLU İÇE AKTARIM (İE#21 B10), idempotent.
     *
     * Gövde: `{"kategoriler": [...]}` — düz liste, nesne listesi ya da ağaç
     * (biçim toleransının gerekçesi KategoriIceAktarim sınıf başlığındadır).
     *
     * İdempotanlık bir kolaylık değil, bir GÜVENLİKTİR: içe aktarımı iki kez
     * koşan kullanıcı kategorileri ikiye katlamamalıdır; kategoriler ürünlere
     * bağlıdır ve mükerrer kayıt raporları sessizce böler.
     */
    public function import(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $veri = $body['kategoriler'] ?? $body['categories'] ?? null;
        if (!is_array($veri) || $veri === []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'kategoriler' => 'İçe aktarılacak kategori listesi boş olamaz.',
            ]);
        }

        $sonuc = (new \App\Services\KategoriIceAktarim($this->categories))->calistir($veri);

        $this->log(
            $request,
            'categories_imported',
            null,
            sprintf('%d eklendi, %d zaten vardı', $sonuc['eklenen'], $sonuc['atlanan']),
        );

        return Response::success($response, [
            'eklenen' => $sonuc['eklenen'],
            'atlanan' => $sonuc['atlanan'],
            'toplam' => count($sonuc['adlar']),
            'uyarilar' => $sonuc['uyarilar'],
        ]);
    }

    /** POST /api/categories */
    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $name = $this->str($body, 'name');

        $error = $this->validateName($name);
        if ($error !== null) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['name' => $error]);
        }
        if ($this->categories->findByName($name) !== null) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'name' => 'Bu isimde bir kategori zaten var.',
            ]);
        }

        $sort = $this->sortValue($body) ?? ($this->categories->maxSort() + 1);
        $id = $this->categories->create($name, $sort);
        $this->log($request, 'category_created', $id, $name);

        return Response::success($response, ['id' => $id, 'name' => $name, 'sort' => $sort, 'product_count' => 0], [], 201);
    }

    /**
     * PATCH /api/categories/{id}
     *
     * @param array<string, string> $args
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $this->intArg($args, 'id');
        $category = $id === null ? null : $this->categories->find($id);
        if ($category === null) {
            return Response::error($response, 'NOT_FOUND', 'Kategori bulunamadı.', 404);
        }

        $body = $this->body($request);
        $fields = [];

        if (array_key_exists('name', $body)) {
            $name = $this->str($body, 'name');
            $error = $this->validateName($name);
            if ($error !== null) {
                return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, ['name' => $error]);
            }
            if ($this->categories->findByName($name, $id) !== null) {
                return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                    'name' => 'Bu isimde başka bir kategori var.',
                ]);
            }
            $fields['name'] = $name;
        }

        $sort = $this->sortValue($body);
        if ($sort !== null) {
            $fields['sort'] = $sort;
        } elseif (array_key_exists('sort', $body)) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'sort' => 'Sıra 0 ile 9999 arasında bir tam sayı olmalı.',
            ]);
        }

        if ($fields === []) {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'body' => 'Güncellenecek alan verilmedi.',
            ]);
        }

        $this->categories->update($id, $fields);
        $this->log($request, 'category_updated', $id, implode(',', array_keys($fields)));

        $fresh = $this->categories->find($id);

        return Response::success($response, [
            'id' => $id,
            'name' => (string) ($fresh['name'] ?? ''),
            'sort' => (int) ($fresh['sort'] ?? 0),
            'product_count' => $this->categories->productCount($id),
        ]);
    }

    /**
     * DELETE /api/categories/{id}
     *
     * @param array<string, string> $args
     */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $this->intArg($args, 'id');
        $category = $id === null ? null : $this->categories->find($id);
        if ($category === null) {
            return Response::error($response, 'NOT_FOUND', 'Kategori bulunamadı.', 404);
        }

        $inUse = $this->categories->productCount($id);
        if ($inUse > 0) {
            return Response::error(
                $response,
                'VALIDATION',
                sprintf('Bu kategori %d üründe kullanılıyor; önce ürünlerin kategorisini değiştirin.', $inUse),
                422,
                [],
                ['product_count' => $inUse],
            );
        }

        $this->categories->delete($id);
        $this->log($request, 'category_deleted', $id, (string) $category['name']);

        return $response->withStatus(204);
    }

    private function validateName(string $name): ?string
    {
        if ($name === '') {
            return 'Kategori adı zorunludur.';
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            return sprintf('Kategori adı en çok %d karakter olabilir.', self::MAX_NAME_LENGTH);
        }

        return null;
    }

    /** @param array<string, mixed> $body */
    private function sortValue(array $body): ?int
    {
        $value = $body['sort'] ?? null;
        if (is_int($value) && $value >= 0 && $value <= 9999) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d{1,4}$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function log(ServerRequestInterface $request, string $action, ?int $id, string $detail): void
    {
        $this->activity->record(
            'category',
            $id,
            $action,
            $detail,
            ClientIp::from($request),
            $this->clock->now(),
            ActivityLog::ACTOR_ADMIN,
            $this->user($request)->id,
        );
    }
}
