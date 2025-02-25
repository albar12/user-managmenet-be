<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\SendOtpMail;
use App\Mail\SendPasswordOtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;


class AuthController extends Controller
{
    // Fungsi untuk register user
    public function register(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'no_whatsapp' => 'required|string|max:255',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first() // Ambil pesan error pertama
            ], 422);
        }

        $rawToken = $request->email . '_' . now()->format('YmdHis'); // Format: email_datetime
        $token = base64_encode($rawToken);

        $otp = rand(100000, 999999);

        // Simpan user ke database
        $user = User::create([
            'nama' => $request->nama,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'no_whatsapp' => $request->no_whatsapp,
            'token' => $token,
            'otp' => $otp,
            'is_active' => false,

        ]);

        Mail::to($user->email)->send(new SendOtpMail($otp));

        return response()->json([
            'message' => 'Registrasi Sukses!, Silahkan verifikasi OTP untuk mengaktifkan akun',
        ], 201);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        if ($user->otp === $request->otp) {
            $user->update([
                'is_active' => true,
                'otp'       => null,  // Hapus OTP setelah verifikasi berhasil
            ]);

            return response()->json([
                'message' => 'Akun berhasil diverifikasi!',
            ], 200);
        }

        return response()->json(['message' => 'Kode OTP salah atau tidak valid.'], 400);
    }

    public function resendOtp(Request $request)
    {
        // ✅ Validasi email
        $request->validate([
            'email' => 'required|email',
        ]);

        // 🔍 Cari user berdasarkan email
        $user = User::where('email', $request->email)->first();

        // ❌ Jika user tidak ditemukan
        if (!$user) {
            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        // ⚡ Jika akun sudah aktif
        if ($user->is_active) {
            return response()->json(['message' => 'Akun sudah aktif. Tidak perlu verifikasi OTP.'], 400);
        }

        // 🔑 Generate OTP baru
        $otpBaru = rand(100000, 999999);

        // 💾 Update OTP di database
        $user->update(['otp' => $otpBaru]);

        // 📩 Kirim OTP ke email
        Mail::to($user->email)->send(new SendOtpMail($otpBaru));

        // 🎉 Respon sukses
        return response()->json([
            'message' => 'Kode OTP baru telah dikirim ke email Anda.',
            'email'   => $user->email
        ], 200);
    }

    public function login(Request $request)
    {
        // ✅ Validasi input
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // 🔍 Cari user berdasarkan email
        $user = User::where('email', $request->email)->first();

        // ❌ Jika user tidak ditemukan
        if (!$user) {
            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        // ❌ Jika password salah
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password salah.'], 401);
        }

        // ⚡ Jika akun belum aktif
        if (!$user->is_active) {
            return response()->json(['message' => 'Akun belum aktif. Silakan verifikasi OTP terlebih dahulu.'], 403);
        }

        // 🎯 Generate token baru
        $rawToken = $user->email . '_' . now()->format('YmdHis');
        $token = base64_encode($rawToken);

        $user->update(['token' => $token]); // 🔄 Simpan token ke database

        // 🎉 Respon sukses dengan data user + token
        return response()->json([
            'message' => 'Login berhasil!',
            'data'    => $user->only(['id', 'nama', 'email', 'no_whatsapp', 'is_active', 'token'])
        ], 200);
    }

    public function getAllUsers(Request $request)
    {
        $query = User::query();

        // 🔎 Filter pencarian berdasarkan nama atau email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%");
            });
        }

        // 📄 Pagination (default 10 per halaman)
        $perPage = $request->get('per_page', 5);
        $users = $query->select('id', 'nama', 'email', 'no_whatsapp', 'is_active', 'created_at')
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

        // 🎉 Respon sukses
        return response()->json([
            'message' => 'Data user berhasil diambil.',
            'data'    => $users
        ], 200);
    }

    public function getUserDetail($id)
    {
        // 🔎 Cari user berdasarkan ID
        $user = User::select('id', 'nama', 'email', 'no_whatsapp', 'is_active', 'created_at', 'updated_at')
                    ->find($id);

        // ❌ Jika user tidak ditemukan
        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan.'
            ], 404);
        }

        // 🎉 Respon sukses jika user ditemukan
        return response()->json([
            'message' => 'Detail user berhasil diambil.',
            'data'    => $user
        ], 200);
    }

    public function getProfileByEmail(Request $request)
    {
        // ✅ Validasi input email
        $request->validate([
            'email' => 'required|email',
        ]);

        // 🔎 Cari user berdasarkan email
        $user = User::select('id', 'nama', 'email', 'no_whatsapp', 'is_active', 'created_at', 'updated_at')
                    ->where('email', $request->email)
                    ->first();

        // ❌ Jika user tidak ditemukan
        if (!$user) {
            return response()->json([
                'message' => 'User dengan email tersebut tidak ditemukan.'
            ], 404);
        }

        // 🎉 Respon sukses
        return response()->json([
            'message' => 'Data profil berhasil diambil.',
            'data'    => $user
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        // ✅ Validasi input
        $request->validate([
            'email'        => 'required|email',
            'nama'         => 'required|string|max:255',
            'no_whatsapp'  => 'required|string|max:20',
        ]);

        // 🔎 Cari user berdasarkan email
        $user = User::where('email', $request->email)->first();

        // ❌ Jika user tidak ditemukan
        if (!$user) {
            return response()->json([
                'message' => 'User dengan email tersebut tidak ditemukan.'
            ], 404);
        }

        // 🔄 Update data user
        $user->update([
            'nama'        => $request->nama,
            'no_whatsapp' => $request->no_whatsapp,
        ]);

        // 🎉 Respon sukses
        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'data'    => $user
        ], 200);
    }

    public function sendOtpForResetPassword(Request $request)
    {
        // ✅ Validasi input
        $request->validate([
            'email' => 'required|email',
        ]);

        // 🔎 Cari user berdasarkan email
        $user = User::where('email', $request->email)->first();

        // ❌ Jika user tidak ditemukan
        if (!$user) {
            return response()->json([
                'message' => 'Email tidak ditemukan.'
            ], 404);
        }

        // 🔄 Buat OTP baru
        $otp = rand(100000, 999999);
        $user->update(['otp' => $otp]);

        // 📩 Kirim email OTP
        Mail::to($user->email)->send(new SendPasswordOtpMail($otp));

        return response()->json([
            'message' => 'Kode OTP telah dikirim ke email. Silakan cek inbox Anda untuk verifikasi.',
            'email'   => $user->email
        ], 200);
    }

    public function verifyOtpForResetPassword(Request $request)
    {
        // ✅ Validasi input
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string',
        ]);

        // 🔎 Cari user berdasarkan email
        $user = User::where('email', $request->email)->first();

        // ❌ Jika user tidak ditemukan
        if (!$user) {
            return response()->json([
                'message' => 'Email tidak ditemukan.'
            ], 404);
        }

        // 🔐 Verifikasi OTP
        if ($user->otp === $request->otp) {
            $user->update([
                'otp' => null, // 🧹 Hapus OTP setelah verifikasi berhasil
            ]);

            return response()->json([
                'message' => 'OTP berhasil diverifikasi. Silakan lanjutkan untuk mengatur ulang password.',
                'email'   => $user->email
            ], 200);
        }

        // ❌ OTP salah atau tidak valid
        return response()->json([
            'message' => 'Kode OTP salah atau tidak valid.'
        ], 400);
    }

    public function updatePassword(Request $request)
    {
        // ✅ Validasi input
        $request->validate([
            'email'                 => 'required|email',
            'new_password'          => 'required|string|min:6|confirmed',
        ]);
// 
        // 🔎 Cari user berdasarkan email
        $user = User::where('email', $request->email)->first();

        // ❌ Jika user tidak ditemukan
        if (!$user) {
            return response()->json([
                'message' => 'Email tidak ditemukan.'
            ], 404);
        }

        // 🔒 Update password
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password berhasil diperbarui. Silakan login dengan password baru Anda.'
        ], 200);
    }

}
