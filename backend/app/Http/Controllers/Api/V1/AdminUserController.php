<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAdminUserRequest;
use App\Http\Requests\Api\V1\StoreItAccountRequest;
use App\Http\Requests\Api\V1\StoreRequesterAccountRequest;
use App\Http\Requests\Api\V1\UpdateAdminUserRequest;
use App\Http\Resources\Api\V1\AdminUserResource;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'exists:roles,key'],
            'is_active' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();

        $query = User::query()->with(['role:id,key,name', 'division:id,code,name', 'branch:id,code,name', 'office:id,name,office_type']);
        if ($search = $validated['search'] ?? null) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }
        if ($role = $validated['role'] ?? null) {
            $query->whereHas('role', fn ($builder) => $builder->where('key', $role));
        }
        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', $validated['is_active']);
        }

        $users = $query->orderBy('name')->paginate($validated['per_page'] ?? 20);

        return ApiResponse::success(
            $request,
            'Accounts retrieved',
            AdminUserResource::collection($users->items()),
            meta: ['pagination' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ]],
        );
    }

    public function options(Request $request): JsonResponse
    {
        return ApiResponse::success($request, 'Account options retrieved', [
            'roles' => Role::query()
                ->where('is_active', true)
                ->withCount(['users as active_user_count' => fn ($query) => $query->where('is_active', true)])
                ->withCount('users')
                ->orderBy('id')
                ->get(['id', 'key', 'name', 'description']),
            'divisions' => Division::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
            'branches' => Branch::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
            'offices' => Office::query()->orderBy('name')->get(['id', 'name', 'office_type']),
            'workflow_role_keys' => ['requester', 'supervisor_it', 'pic_it_support', 'pic_it_develop'],
            'workflow' => [
                ['stage' => 'Diajukan', 'owner_role' => 'requester', 'description' => 'Requester membuat tiket dan melengkapi informasi awal.'],
                ['stage' => 'Analisis & Penugasan', 'owner_role' => 'supervisor_it', 'description' => 'Supervisor IT menganalisis, menentukan prioritas, dan menunjuk PIC.'],
                ['stage' => 'Pengerjaan', 'owner_role' => 'pic_it_support / pic_it_develop', 'description' => 'PIC menangani tiket, mencatat progres, dan meminta informasi bila diperlukan.'],
                ['stage' => 'Pemeriksaan Akhir', 'owner_role' => 'supervisor_it', 'description' => 'Supervisor IT menyetujui hasil atau meminta revisi kepada PIC.'],
                ['stage' => 'Selesai', 'owner_role' => 'requester', 'description' => 'Requester menerima hasil; Supervisor IT dapat membuka kembali jika diperlukan.'],
            ],
        ]);
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $values = $request->validated();
        $values['password'] = Hash::make($values['password']);
        unset($values['password_confirmation']);
        $user = User::query()->create($values);

        return ApiResponse::success($request, 'Account created', new AdminUserResource($this->load($user)), 201);
    }

    public function storeRequester(StoreRequesterAccountRequest $request): JsonResponse
    {
        $values = $request->validated();
        $office = $values['office_mode'] === 'pusat'
            ? Office::query()->pusat()->orderBy('id')->first()
            : Office::query()->cabang()->find($values['office_id']);

        if (! $office) {
            return ApiResponse::validationError($request, [
                'office_id' => ['Kantor Pusat belum tersedia. Buat Kantor Pusat terlebih dahulu.'],
            ]);
        }

        $role = Role::query()->where('key', 'requester')->where('is_active', true)->firstOrFail();
        $user = DB::transaction(fn () => User::query()->create([
            'role_id' => $role->id,
            'office_id' => $office->id,
            'name' => $values['name'],
            'email' => $values['email'],
            'phone' => $values['phone'],
            'password' => Hash::make($values['password']),
            'is_active' => true,
        ]));

        return ApiResponse::success($request, 'Akun Requester berhasil dibuat.', new AdminUserResource($this->load($user)), 201);
    }

    public function storeIt(StoreItAccountRequest $request): JsonResponse
    {
        $values = $request->validated();
        $role = Role::query()->where('key', $values['role'])->where('is_active', true)->firstOrFail();
        $user = DB::transaction(fn () => User::query()->create([
            'role_id' => $role->id,
            'office_id' => null,
            'name' => $values['name'],
            'email' => $values['email'],
            'phone' => $values['phone'],
            'password' => Hash::make($values['password']),
            'is_active' => true,
        ]));

        return ApiResponse::success($request, 'Akun IT berhasil dibuat.', new AdminUserResource($this->load($user)), 201);
    }

    public function update(UpdateAdminUserRequest $request, User $user): JsonResponse
    {
        $values = $request->validated();
        $newRole = Role::query()->findOrFail($values['role_id']);
        $removesActiveSuperadmin = $user->is_active
            && $user->role?->key === 'superadmin'
            && (! $values['is_active'] || $newRole->key !== 'superadmin');

        if ($request->user()->is($user) && (! $values['is_active'] || $newRole->key !== 'superadmin')) {
            return ApiResponse::validationError($request, ['is_active' => ['Anda tidak dapat menonaktifkan atau mengubah role akun sendiri.']]);
        }
        if ($removesActiveSuperadmin && User::query()->where('is_active', true)->whereHas('role', fn ($query) => $query->where('key', 'superadmin'))->count() <= 1) {
            return ApiResponse::validationError($request, ['role_id' => ['Minimal satu akun Super Admin aktif harus tersedia.']]);
        }

        if (! empty($values['password'])) {
            $values['password'] = Hash::make($values['password']);
        } else {
            unset($values['password']);
        }
        unset($values['password_confirmation']);

        DB::transaction(fn () => $user->update($values));

        return ApiResponse::success($request, 'Account updated', new AdminUserResource($this->load($user->refresh())));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if (! $request->user()->hasRole('superadmin')) {
            return ApiResponse::error($request, 'Hanya Super Admin yang dapat menghapus akun.', 'FORBIDDEN', 403);
        }

        if ($request->user()->is($user)) {
            return ApiResponse::error($request, 'Anda tidak dapat menghapus akun sendiri.', 'SELF_DELETE_FORBIDDEN', 409);
        }

        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->update(['is_active' => false]);
            $lockedUser->delete();
        });

        return ApiResponse::success($request, 'Akun berhasil dihapus.');
    }

    private function load(User $user): User
    {
        return $user->load(['role:id,key,name', 'division:id,code,name', 'branch:id,code,name', 'office:id,name,office_type']);
    }
}
