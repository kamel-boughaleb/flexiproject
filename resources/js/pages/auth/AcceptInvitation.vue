<script setup lang="ts">
import AvatarPicker from '@/components/AvatarPicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import type { MiniUser } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    token: string;
    user: MiniUser & { email: string };
    inviter: string | null;
    googleEnabled: boolean;
}>();

const form = useForm({
    name: props.user.name,
    photo: null as File | null,
    password: '',
    password_confirmation: '',
});

const preview = computed(() => ({ ...props.user, name: form.name || props.user.name }));
const submit = () => form.post(route('invitations.accept', props.token), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <AuthBase
        :title="$t(`Bienvenue dans l'équipe`)"
        :description="
            inviter
                ? $t(':name vous invite à collaborer. Créez votre compte pour commencer.', { name: inviter })
                : $t('Créez votre compte pour commencer.')
        "
    >
        <Head :title="$t(`Accepter l'invitation`)" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label>{{ $t('Votre photo') }}</Label>
                    <AvatarPicker
                        v-model="form.photo"
                        :user="preview"
                        :hint="$t('Ajoutez une photo pour que vos collègues vous reconnaissent. En attendant, cet avatar vous représente.')"
                    />
                    <InputError :message="form.errors.photo" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ $t('Adresse e-mail') }}</Label>
                    <Input id="email" type="email" :model-value="user.email" readonly class="bg-muted" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">{{ $t('Nom') }}</Label>
                    <Input id="name" v-model="form.name" required autocomplete="name" :placeholder="$t('Prénom Nom')" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ $t('Mot de passe') }}</Label>
                    <Input
                        id="password"
                        type="password"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        :placeholder="$t('Mot de passe')"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">{{ $t('Confirmer le mot de passe') }}</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        :placeholder="$t('Confirmer le mot de passe')"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ $t('Créer mon compte') }}
                </Button>
            </div>

            <template v-if="googleEnabled">
                <div class="flex items-center gap-3 text-xs text-muted-foreground">
                    <span class="h-px flex-1 bg-border" />{{ $t('ou') }}<span class="h-px flex-1 bg-border" />
                </div>
                <Button as="a" :href="route('google.redirect')" variant="outline" class="w-full">{{
                    $t('Continuer avec Google (:email)', { email: user.email })
                }}</Button>
            </template>
        </form>
    </AuthBase>
</template>
