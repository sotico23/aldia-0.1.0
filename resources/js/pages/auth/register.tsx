import { Form, Head, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import InputError from '@/components/input-error';
import { SocialLoginButtons } from '@/components/social-login-buttons';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PasswordInput } from '@/components/ui/password-input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { store } from '@/routes/register';

interface Country {
    code: string;
    name: string;
    currency_code: string;
    currency_symbol: string;
    phone_code: string;
}

interface RegisterPageProps {
    countries: Country[];
    [key: string]: unknown;
}

function getFlagEmoji(countryCode: string): string {
    const codePoints = [...countryCode.toUpperCase()].map(
        (c) => 0x1f1e6 - 65 + c.charCodeAt(0),
    );
    return String.fromCodePoint(...codePoints);
}

export default function Register() {
    const { countries } = usePage<RegisterPageProps>().props;
    const params = new URLSearchParams(window.location.search);
    const referralCode = params.get('ref') || '';

    const defaultCountry = countries.length > 0 ? countries[0].code : 'CL';
    const [selectedCountry, setSelectedCountry] = useState(defaultCountry);
    const [phoneLocalNumber, setPhoneLocalNumber] = useState('');

    const selectedCountryData = countries.find((c) => c.code === selectedCountry);
    const fullPhone = useMemo(() => {
        if (!phoneLocalNumber) return '';
        const cleanNumber = phoneLocalNumber.replace(/\s+/g, '');
        return `${selectedCountryData?.phone_code ?? '+56'}${cleanNumber}`;
    }, [phoneLocalNumber, selectedCountryData]);

    return (
        <AuthLayout
            title="Crear una cuenta"
            description="Ingresa tus datos a continuación para crear tu cuenta"
        >
            <Head title="Registrarse" />
            <Form
                action={store().url}
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre *</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Nombre completo"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    Correo electrónico *
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="correo@ejemplo.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="country">País *</Label>
                                <Select
                                    name="country"
                                    defaultValue={defaultCountry}
                                    onValueChange={(value) => setSelectedCountry(value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona tu país" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {countries.map((country) => (
                                            <SelectItem key={country.code} value={country.code}>
                                                {getFlagEmoji(country.code)} {country.name} ({country.currency_symbol} {country.currency_code})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {selectedCountryData && (
                                    <p className="text-xs text-muted-foreground">
                                        Moneda: {selectedCountryData.currency_symbol} {selectedCountryData.currency_code}
                                    </p>
                                )}
                                <InputError message={errors.country} className="mt-2" />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="business_name">
                                    Nombre de empresa (Opcional)
                                </Label>
                                <Input
                                    id="business_name"
                                    type="text"
                                    tabIndex={3}
                                    autoComplete="organization"
                                    name="business_name"
                                    placeholder="Si aplica, ingresa el nombre de tu empresa"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Si no se provee, se usará tu nombre como nombre de empresa.
                                </p>
                                <InputError message={errors.business_name} className="mt-2" />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="telefono">
                                    Número de teléfono (Opcional)
                                </Label>
                                <input type="hidden" name="telefono" value={fullPhone} />
                                <div className="flex gap-2">
                                    <Select
                                        defaultValue={defaultCountry}
                                        onValueChange={(value) => setSelectedCountry(value)}
                                    >
                                        <SelectTrigger className="w-[140px] shrink-0">
                                            <SelectValue>
                                                {selectedCountryData && (
                                                    <span className="flex items-center gap-1.5">
                                                        <span>{getFlagEmoji(selectedCountryData.code)}</span>
                                                        <span>{selectedCountryData.phone_code}</span>
                                                    </span>
                                                )}
                                            </SelectValue>
                                        </SelectTrigger>
                                        <SelectContent>
                                            {countries.map((country) => (
                                                <SelectItem key={country.code} value={country.code}>
                                                    <span className="flex items-center gap-1.5">
                                                        <span>{getFlagEmoji(country.code)}</span>
                                                        <span>{country.phone_code}</span>
                                                        <span className="text-muted-foreground">{country.name}</span>
                                                    </span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        id="telefono"
                                        type="tel"
                                        tabIndex={4}
                                        autoComplete="tel"
                                        value={phoneLocalNumber}
                                        onChange={(e) => setPhoneLocalNumber(e.target.value)}
                                        placeholder={selectedCountryData?.code === 'US' ? '202 555 0123' : '9 1234 5678'}
                                        className="flex-1"
                                    />
                                </div>
                                {fullPhone && (
                                    <p className="text-xs text-muted-foreground">
                                        Número completo: {fullPhone}
                                    </p>
                                )}
                                <InputError message={errors.telefono} className="mt-2" />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Contraseña *</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Contraseña"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirmar contraseña *
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={6}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirmar contraseña"
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="referral_code">Código de referido (Opcional)</Label>
                                <Input
                                    id="referral_code"
                                    type="text"
                                    tabIndex={7}
                                    name="referral_code"
                                    placeholder="Ej. ABCD123"
                                    defaultValue={referralCode}
                                    readOnly={!!referralCode}
                                    className={referralCode ? 'bg-muted cursor-not-allowed' : ''}
                                />
                                <InputError message={errors.referral_code} className="mt-2" />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={8}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Crear cuenta
                            </Button>
                        </div>

                        <SocialLoginButtons />

                        <div className="text-center text-sm text-muted-foreground">
                            ¿Ya tienes una cuenta?{' '}
                            <TextLink href={login.url()} tabIndex={9}>
                                Iniciar sesión
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
