<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use F9Web\ApiResponseHelpers;
use App\Models\VerificationCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Candidate\CandidateResource;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Notifications\Api\ResetPassword;
use Illuminate\Support\Facades\Notification;
use App\Notifications\CompanyCreatedNotification;
use App\Notifications\CandidateCreateNotification;
use App\Notifications\Admin\NewUserRegisteredNotification;
use App\Notifications\CompanyCreateApprovalPendingNotification;
use App\Notifications\CandidateCreateApprovalPendingNotification;
use Firebase\Auth\Token\Exception\InvalidToken;

class AuthController extends Controller
{
    use ApiResponseHelpers;

    public function login(Request $request)
{
    $request->validate([
        'login' => 'required', // Can be email or phone_number
        'password' => 'required'
    ]);

    // Determine if login is email or phone_number
    $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) 
        ? 'email' 
        : 'phone_number';

    // Check if the user exists with the given credential
    $user = User::where($field, $request->login)->first();

    if (!$user) {
        return $this->respondUnAuthenticated('Invalid credentials');
    }

    // Verify password
    if (!Hash::check($request->password, $user->password)) {
        return $this->respondUnAuthenticated('Invalid credentials');
    }

    // Check if user is active
    if (!$user->status) {
        return $this->respondUnAuthenticated('Your account is inactive');
    }

    // Create token
    $token = $user->createToken('job-pilot')->plainTextToken;

    return $this->respondWithSuccess([
        'data' => [
            'token' => $token,
            'message' => 'Login Succeeded',
            'user' => $user->role == 'candidate' 
                ? new CandidateResource($user->candidate) 
                : new CompanyResource($user->company)
        ]
    ]);
}
  /*  public function login(Request $request){
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            $token =  $user->createToken('job-pilot')->plainTextToken;
           
            return $this->respondWithSuccess([
                'data' => [
                    'token' => $token,
                    'message' => 'Login Succeeded',
                    'user' => $user->role == 'candidate' ? new CandidateResource($user->candidate) : new CompanyResource($user->company)
                ]
            ]);
        }else{
            return $this->respondUnAuthenticated('Invalid Credentials');
        }
    }*/


    public function getUserInfo(Request $request)
    {
        $user = auth('sanctum')->user();

        if($user)
        {
            $token = $request->bearerToken();

            return $this->respondWithSuccess([
                'data' => [
                    'token' => $request->bearerToken(),
                    'message' => 'User data retrieved successfully',
                    'user' => $user->role == 'candidate' ? new CandidateResource($user->candidate) : new CompanyResource($user->company)

                ]
            ]);
        }else{
                return $this->respondUnAuthenticated('Unauthenticated User');

        }
    }

    public function register(Request $request){
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone_number' => 'required|unique:users,phone_number',
        ]);

        $newUsername = Str::slug($request->name);
        $oldUserName = User::where('username', $newUsername)->first();

        if ($oldUserName) {
            $username = Str::slug($newUsername) . '_' . Str::random(5);
        } else {
            $username = Str::slug($newUsername);
        }

        $user = User::create([
            'role' => $request->role == 'candidate' ? 'candidate' : 'company',
            'name' => $request->name,
            'username' => $username,
            'email' => $request->email,
            'phone_number'=>$request->phone_number,
            'password' => Hash::make($request->password),
        ]);

        // $contactInfo = $user->contactInfo()->create([
        //     'phone' => $request->phone_number,
        //     'secondary_phone' => '',
        //     'email' => $request->email,
        //     'secondary_email' => '',
        // ]);
        
        // Check if contact info was created
        // if (!$contactInfo) {
        //     return response()->json(['error' => 'Failed to save contact info'], 500);
        // }

        try {
            $admins = Admin::all();
            foreach ($admins as $admin) {
                Notification::send($admin, new NewUserRegisteredNotification($admin, $user));
            }
        } catch (\Throwable $th) {}

        // if mail configured, send notification to candidate and company
        if (checkMailConfig()) {
            if ($user->role == "candidate") {
                $candidate_account_auto_activation_enabled = Setting::where("candidate_account_auto_activation", 1)->count();

                if ($candidate_account_auto_activation_enabled) {
                    Notification::route('mail', $user->email)->notify(new CandidateCreateNotification($user, $request->password));
                } else {
                    Notification::route('mail', $user->email)->notify(new CandidateCreateApprovalPendingNotification($user, $request->password));
                }
            } elseif ($user->role == "company") {
                $employer_auto_activation_enabled = Setting::where("employer_auto_activation", 1)->count();

                if ($employer_auto_activation_enabled) {
                    Notification::route('mail', $user->email)->notify(new CompanyCreatedNotification($user, $request->password));
                } else {
                    Notification::route("mail", $user->email)->notify(new CompanyCreateApprovalPendingNotification($user, $request->password));
                }
            }
        }
        $token = $user->createToken('job-pilot')->plainTextToken;
        if ($user) {
            return $this->respondWithSuccess([
                'data' => [
                    'token' => $token,
                    'message' => 'Registration Succeeded',
                    'user' => $user->role == 'candidate' 
                         ? new CandidateResource($user->candidate) 
                         : new CompanyResource($user->company)
        ]
                // 'data' => $user,
                // 'token' => $token,
                // 'message' => 'Registration Succeeded'
            ]);
        }

        return $this->respondError('Registration Failed');
    }

// update is user OTP verify or not
public function isUserVerified(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'is_verified' => 'required' // Accepts true or false
    ]);

    $email = $request->input('email'); 

    $user = User::where('email', $email)->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User not found',
        ], 404);
    }

    // Convert 'is_verified' to a boolean
    $user->is_verified = filter_var($request->input('is_verified'), FILTER_VALIDATE_BOOLEAN);
    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'User verification status updated successfully',
        'data' => [
            'is_verified' => $user->is_verified,
        ],
    ], 200);
}    


public function isCompleteCard(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'is_card_complete' => 'required' // Accepts true or false
    ]);

    $email = $request->input('email');

    $user = User::where('email', $email)->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User not found',
        ], 404);
    }

    // Convert 'is_complete_card' to a boolean
    $user->is_card_complete = filter_var($request->input('is_card_complete'), FILTER_VALIDATE_BOOLEAN);
    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'User complete card status updated successfully',
        'data' => [
            'is_card_complete' => $user->is_card_complete,
        ],
    ], 200);
}


    public function profile()
    {
        $user = Auth::user();

        return $this->respondWithSuccess([
            'data' => $user
        ]);
    }

    public function sendResetCodeEmail(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|string|email|exists:users,email',
        ]);

        $customer = User::where('email', $request->email)->first();
        $code = rand(100000, 999999);

        $customer->verificationCodes()->create([
            'code' => $code,
            'type' => 'reset_password'
        ]);

        if (checkMailConfig()) {
            $customer->notify(new ResetPassword($code));
        }

        return $this->respondWithSuccess([
            'data' => [
                'message' => 'We have emailed you password reset code',

                // testing only should remove in production
                'code' => $code
            ]
        ]);
    }

    public function reset(Request $request)
    {
        $this->validate($request, [
            'code' => 'required',
            'email' => "required|string|max:100|email|exists:users,email",
            'password' => "required|min:8|max:50",
        ]);

        $customer = User::where('email', $request->email)->first();
        $verificationCode = VerificationCode::reset()
                ->where('user_id', $customer->id)
                ->where('code', $request->code)
                ->first();

        if (!$verificationCode) {
            return $this->respondError('Invalid code');
        }elseif ($verificationCode && now()->isAfter($verificationCode->expire_at)) {
            return $this->respondError('Code expired');
        }

        if ($customer) {
            $customer->update([
                'password' => bcrypt($request->password),
            ]);

            return $this->respondWithSuccess([
                'data' => [
                    'message' => 'Password reset successfully'
                ]
            ]);
        }

        return $this->respondNotFound('Invalid code');
    }

    public function socialLogin(Request $request) {

        // Launch Firebase Auth
        $auth = app('firebase.auth');

        // Retrieve the Firebase credential's token
        $idTokenString = $request->input('Firebasetoken');

        try {
            // Try to verify the Firebase credential token with Google
            $verifiedIdToken = $auth->verifyIdToken($idTokenString);
        } catch (\InvalidArgumentException $e) {
            // If the token has the wrong format
            return response()->json([
                'message' => 'Unauthorized - Can\'t parse the token: ' . $e->getMessage()
            ], 401);
        } catch (InvalidToken $e) {
            // If the token is invalid (expired ...)
            return response()->json([
                'message' => 'Unauthorized - Token is invalide: ' . $e->getMessage()
            ], 401);
        }

        // Retrieve the UID (User ID) from the verified Firebase credential's token
        $uid = $verifiedIdToken->getClaim('sub');

        // Retrieve the user model linked with the Firebase UID
        $user = User::where('firebase_uid', $uid)->first();

        // If the user doesn't exist, create a new user (you may customize this)
        try {
            if (!$user) {
                $user = User::create([
                    'firebase_uid' => $uid,
                    'role' => $request->input('role'),
                    'name' => $request->input('name'),
                    'email' => $request->input('email'),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating user: ' . $e->getMessage()
            ], 500);
        }
        
        // Create a Personal Access Token using Sanctum
        $token =  $user->createToken('job-pilot')->plainTextToken;

        return $this->respondWithSuccess([
            'data' => [
                'token' => $token,
                'message' => 'User data retrieved successfully',
                'user' => $user->role == 'candidate' ? new CandidateResource($user->candidate) : new CompanyResource($user->company)
            ]
        ]);
       
      
    }
}
