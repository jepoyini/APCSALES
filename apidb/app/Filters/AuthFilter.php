<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use App\Models\UserModel;
// use App\Helpers\AuthHelper;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {

  // return response()->setJSON([
  //                'status' => 'error', 
  //                'message' => 'TSET Access Denied.'])->setStatusCode(401);

       $userid = $request->getVar('uid');
       if ($userid == 0) 
       {
            return response()->setJSON([
                'status' => 'error', 
                'message' => '1Access Denied.'])->setStatusCode(401);
       }
     //  $request->getHeader('X-CSRF-TOKEN');
        $token_header = $request->getHeaderLine('Authorization'); // Get Bearer  
        if (!$token_header) {
            return response()->setJSON([
                'status' => 'error', 
                'message' => '2Access Denied.'])->setStatusCode(401);
        }
    
        // ✅ Remove "Bearer " prefix
        $token = str_replace('Bearer ', '', $token_header);
        $hash_token = hash('sha256', $token);

        // ✅ Validate token
        $userModel = new UserModel();
        $user = $userModel
            ->where('csrf_token', $token)
            ->where('id', $userid)
            ->first();
            
        if (!$user) {

            return response()->setJSON([
                'status' => 'error', 
                'message' => '3Access Denied!'
                //'authheader'=>$token_header
                 ])->setStatusCode(401);
        }
        session()->set('uid', $userid);
 

        // ✅ Allow API requests, but still protect CSRF for non-API routes
        // if (!str_starts_with($request->getUri()->getPath(), 'api')) {
        //     if ($request->getMethod() !== 'get' && !$request->getHeader('X-CSRF-TOKEN')) {
        //         return response()->setJSON([
        //             'status' => 'error', 
        //             'message' => 'Missing CSRF token.'])->setStatusCode(403);
        //     }
        // }


        //AuthHelper::setAuthUser($user);
               // if (!AuthHelper::getAuthUser()) {
        //     return $this->response->setJSON(['status' => 'error', 'message' => 'Unauthorized'])->setStatusCode(401);
        // }

    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed after response
    }
}
