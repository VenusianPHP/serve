<?php

namespace Mariner\Core\Auth;

use Mariner\Auth\Events\Verified;
use Mariner\Validation\FormRequest;
use Mariner\Validation\Validator;

class EmailVerificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (! hash_equals((string) $this->user()->getKey(), (string) $this->route('id'))) {
            return false;
        }

        if (! hash_equals(sha1($this->user()->getEmailForVerification()), (string) $this->route('hash'))) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }

    /**
     * Fulfill the email verification request.
     *
     * @return void
     */
    public function fulfill()
    {
        if (! $this->user()->hasVerifiedEmail()) {
            $this->user()->markEmailAsVerified();

            $this->container->make('signals')->dispatch(new Verified($this->user()));
        }
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Mariner\Validation\Validator  $validator
     * @return \Mariner\Validation\Validator
     */
    public function withValidator(Validator $validator)
    {
        return $validator;
    }
}
