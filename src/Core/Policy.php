<?php

namespace App\Core;

class Policy
{
    
    public static function authorize(
        string $policyClass,
        string $method,
        array $user,
        array $resource
    ): void {

    if (!empty($_SESSION['is_super_admin'])) {
    return;
}

        $policy = new $policyClass();

        if (!method_exists($policy, $method)) {
            throw new \Exception("Policy method {$method} not found.");
        }

        if (!$policy->$method($user, $resource)) {

            http_response_code(403);
            die("403 Forbidden — Policy denied.");
        }
    }


    public static function for(string $resource): string
{
    return "App\\Policies\\" . ucfirst($resource) . "Policy";
}

}
