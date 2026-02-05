<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    branding: Object,
});

const primaryColor = computed(() => props.branding?.colors?.primary || '#3B82F6');
const secondaryColor = computed(() => props.branding?.colors?.secondary || '#1E40AF');

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Login" />

    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8">
            <div class="text-center mb-8">
                <img
                    v-if="branding?.logo"
                    :src="branding.logo"
                    alt="Logo"
                    class="h-20 max-w-72 object-contain mx-auto mb-4"
                />
                <h1
                    class="text-2xl font-bold"
                    :style="{ color: primaryColor }"
                >
                    Webmail
                </h1>
                <p class="text-gray-600 mt-2">Entre com suas credenciais</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        E-mail
                    </label>
                    <input
                        id="email"
                        type="email"
                        v-model="form.email"
                        required
                        autofocus
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:border-transparent"
                        :style="{ '--tw-ring-color': primaryColor }"
                        placeholder="seu@email.com"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Senha
                    </label>
                    <input
                        id="password"
                        type="password"
                        v-model="form.password"
                        required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:border-transparent"
                        :style="{ '--tw-ring-color': primaryColor }"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div class="flex items-center">
                    <input
                        id="remember"
                        type="checkbox"
                        v-model="form.remember"
                        class="h-4 w-4 border-gray-300 rounded"
                        :style="{ accentColor: primaryColor }"
                    />
                    <label for="remember" class="ml-2 block text-sm text-gray-700">
                        Lembrar-me
                    </label>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 transition-colors"
                    :style="{
                        backgroundColor: primaryColor,
                        '--tw-ring-color': primaryColor,
                    }"
                    @mouseenter="$event.target.style.backgroundColor = secondaryColor"
                    @mouseleave="$event.target.style.backgroundColor = primaryColor"
                >
                    <span v-if="form.processing">Entrando...</span>
                    <span v-else>Entrar</span>
                </button>
            </form>
        </div>
    </div>
</template>
