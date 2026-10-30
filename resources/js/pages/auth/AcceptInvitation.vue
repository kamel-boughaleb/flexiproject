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
        title="Bienvenue dans l'équipe"
        :description="inviter ? `${inviter} vous invite à collaborer. Créez votre compte pour commencer.` : 'Créez votre compte pour commencer.'"
    >
        <Head title="Accepter l'invitation" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label>Votre photo</Label>
                    <AvatarPicker
                        v-model="form.photo"
                        :user="preview"
                        hint="Ajoutez une photo pour que vos collègues vous reconnaissent. En attendant, cet avatar vous représente."
                    />
                    <InputError :message="form.errors.photo" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Adresse e-mail</Label>
                    <Input id="email" type="email" :model-value="user.email" readonly class="bg-muted" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">Nom</Label>
                    <Input id="name" v-model="form.name" required autocomplete="name" placeholder="Prénom Nom" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Mot de passe</Label>
                    <Input id="password" type="password" v-model="form.password" required autocomplete="new-password" placeholder="Mot de passe" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmer le mot de passe</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Confirmer le mot de passe"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Créer mon compte
                </Button>
            </div>

            <template v-if="googleEnabled">
                <div class="flex items-center gap-3 text-xs text-muted-foreground">
                    <span class="h-px flex-1 bg-border" />ou<span class="h-px flex-1 bg-border" />
                </div>
                <Button as="a" :href="route('google.redirect')" variant="outline" class="w-full">Continuer avec Google ({{ user.email }})</Button>
            </template>
        </form>
    </AuthBase>
</template>
