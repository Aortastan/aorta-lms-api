<?php

namespace Tests\Feature;

use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;
    
    // public function setUp(): void
    // {
    //     parent::setUp();
    // }

    /**
     *Test successful registration with good credentials
    */
    public function test_register(){
        $password = $this->faker->password(8,20);
        $goodUserData = [
            'name' => $this->faker->name,
            'email' => $this->faker->safeEmail,
            'password' => $password,
            'password_confirmation' => $password,
        ];
        //try to register
        $response = $this->json('POST',route('api.register'), $goodUserData);
        //for debugging
        if($response->status() !== 200){
            dd($response->getContent());
        }
        //Assert that it is successful
        $response->assertStatus(200);
        //check for token in the response
        $this->assertArrayHasKey('access_token', $response->json());
    }

    /**
     *Test registration with invalid email
    */
    public function test_register_with_invalid_email(){
        $password = $this->faker->password(8,20);
        $badUserData = [
            'name' => $this->faker->name,
            'email' => 'notAValidEmail@',
            'password' => $password,
            'password_confirmation' => $password, //same as above
        ];
        //try to register
        $response = $this->json('POST',route('api.register'), $badUserData);
        //for debugging
        if($response->status() !== 422){
            dd($response->getContent());
        }
        //Assert that it is NOT successful
        $response->assertStatus(422);
    }

     /**
     *Test registration with invalid email
    */
    public function test_register_with_not_matching_password(){
        $badUserData = [
            'name' => $this->faker->name,
            'email' => $this->faker->safeEmail,
            'password' => $this->faker->password(8,20),
            'password_confirmation' => $this->faker->password(8,20), //diff from the first one
        ];
        //Send post request
        $response = $this->json('POST',route('api.register'), $badUserData);
        //for debugging
        if($response->status() !== 422){
            dd($response->getContent());
        }
        //Assert that it is NOT successful, because the password confirmation does not match
        $response->assertStatus(422);
    }


    /**
     *Test Successful login
    */
    public function test_login()
    {
        $password = $this->faker->password(8,20);
        $user = factory(User::class)->create([
            'password' => bcrypt($password)
        ]);

        //Try to log in
        $response = $this->json('POST',route('api.authenticate'), [
            'email' => $user->email,
            'password' => $password,
        ]);
        //for debugging
        if($response->status() !== 200){
            dd($response->getContent());
        }
        //Assert that it succeeded and received the token
        $response->assertStatus(200);
        $this->assertArrayHasKey('access_token',$response->json());
    }

    /**
     *Test Login with Bad Email format
    */
    public function test_login_with_bad_email()
    {
        $password = $this->faker->password(8,20);
        $user = factory(User::class)->create([
            'password' => bcrypt($password)
        ]);

        //Try to log in
        $response = $this->json('POST',route('api.authenticate'), [
            'email' => str_replace('@',".", $user->email),
            'password' => $password,
        ]);
        //for debugging
        if($response->status() !== 422){
            dd($response->getContent());
        }
        //Assert that it did NOT succeed because email is invalid
        $response->assertStatus(422);
    }

    /**
     *Test login with wrong password
    */
    public function test_login_with_wrong_email()
    {
        $password = $this->faker->password(8,20);
        $user = factory(User::class)->create([
            'password' => bcrypt($password)
        ]);
        //Try to log in
        $response = $this->json('POST',route('api.authenticate'), [
            'email' => "test".$user->email,
            'password' => $password,
        ]);
        //for debugging
        if($response->status() !== 401){
            dd($response->getContent());
        }
        //Assert that it did NOT succeed because email is invalid
        $response->assertStatus(401);
    }

    /**
     *Test login with wrong password
    */
    public function test_login_with_wrong_password()
    {
        $user = factory(User::class)->create();
        //Try to log in
        $response = $this->json('POST',route('api.authenticate'), [
            'email' => $user->email,
            'password' => $this->faker->password(8,20),
        ]);
        //for debugging
        if($response->status() !== 401){
            dd($response->getContent());
        }
        //Assert that it did NOT succeed because email is invalid
        $response->assertStatus(401);
    }
}
