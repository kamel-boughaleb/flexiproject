<script setup lang="ts">
import GoogleButton from '@/components/GoogleButton.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CircleAlert, Eye, EyeOff, LockKeyhole, Mail } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage();
const googleError = computed(() => (page.props.errors as Record<string, string>).google);
// Compte désactivé : affiché après une déconnexion forcée ou un lien d'invitation refusé.
const accountError = computed(() => (page.props.errors as Record<string, string>).account);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});
const showPassword = ref(false);
const failed = computed(() => !!(form.errors.email || form.errors.password || accountError.value));

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const field = (error: boolean) => [
    'h-[52px] w-full rounded-[14px] border-2 bg-background pl-11 pr-4 text-[15px] font-semibold outline-none transition placeholder:font-medium placeholder:text-app-muted focus:border-info focus:ring-4 focus:ring-info-tint',
    error ? 'border-brand-active' : 'border-app-border-strong',
];
</script>

<template>
    <AuthBase title="Bon retour parmi nous" description="Connectez-vous pour retrouver vos projets et vos tâches.">
        <Head title="Connexion" />

        <div v-if="status" role="status" class="rounded-2xl bg-success-tint px-4 py-3 text-sm font-bold text-success-text">
            {{ status }}
        </div>
        <div v-if="failed" role="alert" class="flex items-start gap-2.5 rounded-2xl bg-brand-tint px-4 py-3 text-sm font-bold text-brand-active">
            <CircleAlert class="mt-px size-[18px] shrink-0" />
            {{ form.errors.email || form.errors.password || accountError }}
        </div>

        <GoogleButton />
        <InputError :message="googleError" class="-mt-3 text-center" />
        <div class="flex items-center gap-3 text-xs font-bold tracking-[.04em] text-ink-soft">
            <span class="h-px flex-1 bg-app-border" />OU AVEC VOTRE E-MAIL<span class="h-px flex-1 bg-app-border" />
        </div>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-1.5 block text-sm font-bold">Adresse e-mail</label>
                <div class="relative">
                    <Mail class="pointer-events-none absolute left-4 top-1/2 size-[18px] -translate-y-1/2 text-ink-soft" />
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="prenom@flexilivre.com"
                        :aria-invalid="failed"
                        :class="field(failed)"
                    />
                </div>
            </div>

            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="password" class="text-sm font-bold">Mot de passe</label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm font-bold text-info-active no-underline hover:underline"
                    >
                        Mot de passe oublié ?
                    </TextLink>
                </div>
                <div class="relative">
                    <LockKeyhole class="pointer-events-none absolute left-4 top-1/2 size-[18px] -translate-y-1/2 text-ink-soft" />
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        autocomplete="current-password"
                        placeholder="Votre mot de passe"
                        :aria-invalid="failed"
                        :class="[field(failed), 'pr-12']"
                    />
                    <button
                        type="button"
                        class="absolute right-1.5 top-1/2 flex size-10 -translate-y-1/2 items-center justify-center rounded-icon text-ink-soft hover:bg-app-hover"
                        :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                        :aria-pressed="showPassword"
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="size-[18px]" /><Eye v-else class="size-[18px]" />
                    </button>
                </div>
            </div>

            <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm font-semibold">
                <input v-model="form.remember" type="checkbox" class="size-5 rounded accent-[var(--primary-active)]" />
                Se souvenir de moi
            </label>

            <Button type="submit" size="lg" class="h-14 w-full disabled:bg-brand-hover disabled:opacity-100" :disabled="form.processing">
                {{ form.processing ? 'Connexion…' : 'Se connecter' }}
            </Button>

            <p class="text-center text-sm text-ink-soft">Pas encore de compte ? L'accès se fait sur invitation d'un administrateur.</p>
        </form>
    </AuthBase>
</template>
