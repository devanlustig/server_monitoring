<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $server = $this->route('server');

        return [
            'name' => ['required', 'string', 'max:255'],

            'hostname' => [
                'required',
                'string',
                'max:255',
                Rule::unique('monitored_servers', 'hostname')
                    ->ignore($server),
            ],

            'ssh_port' => ['required', 'integer', 'between:1,65535'],
            'ssh_username' => ['required', 'string', 'max:255'],
            'ssh_password' => [
                $server ? 'nullable' : 'required',
                'string',
                'max:4096',
            ],
            'environment' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
