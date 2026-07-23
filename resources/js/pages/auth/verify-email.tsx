// Components
import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { logout } from '@/routes';

const verificationSend = {
    form: () => ({
        action: '/email/verification-notification',
        method: 'post' as const,
    }),
};

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <AuthLayout
            title="Verifica tu correo electrónico"
            description="Por favor, verifica tu dirección de correo electrónico haciendo clic en el enlace que te acabamos de enviar."
        >
            <Head title="Verificación de correo electrónico" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    Se ha enviado un nuevo enlace de verificación a la dirección
                    de correo electrónico que proporcionaste durante el
                    registro.
                </div>
            )}

            <Form
                {...verificationSend.form()}
                className="space-y-6 text-center"
            >
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            Reenviar correo de verificación
                        </Button>

                        <TextLink
                            href={logout().url}
                            method="post"
                            as="button"
                            className="mx-auto block text-sm"
                        >
                            Cerrar sesión
                        </TextLink>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
