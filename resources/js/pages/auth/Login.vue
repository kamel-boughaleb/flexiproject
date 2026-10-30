<script setup lang="ts">
import GoogleButton from '@/components/GoogleButton.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage();
const googleError = computed(() => (page.props.errors as Record<string, string>).google);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Bon retour parmi nous" description="Connectez-vous pour retrouver vos projets et vos tâches.">
        <Head title="Connexion" />

        <div v-if="status" class="rounded-lg bg-green-50 px-3 py-2 text-center text-sm font-medium text-green-700">
            {{ status }}
        </div>

        <GoogleButton />
        <InputError :message="googleError" class="-mt-3 text-center" />
        <div class="flex items-center gap-3 text-xs uppercase tracking-wide text-muted-foreground">
            <span class="h-px flex-1 bg-border" />ou avec votre e-mail<span class="h-px flex-1 bg-border" />
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Adresse e-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autofocus
                        tabindex="1"
                        autocomplete="email"
                        v-model="form.email"
                        placeholder="prenom@flexilivre.com"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Mot de passe</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm" tabindex="5">
                            Mot de passe oublié ?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                        placeholder="Mot de passe"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between" tabindex="3">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" v-model:checked="form.remember" tabindex="4" />
                        <span>Se souvenir de moi</span>
                    </Label>
                </div>

                <Button type="submit" class="mt-2 h-11 w-full bg-orange-500 text-white hover:bg-orange-600" tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Se connecter
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Pas encore de compte ?
                <TextLink :href="route('register')" :tabindex="5">Créer un compte</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
