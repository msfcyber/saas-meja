import { Form, Head } from "@inertiajs/react";
import InputError from "@/components/input-error";
import PasswordInput from "@/components/password-input";
import TextLink from "@/components/text-link";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { login } from "@/routes";
import { store } from "@/routes/register";

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Daftar" />
            <Form
                {...store.form()}
                resetOnSuccess={["password", "password_confirmation"]}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama lengkap</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Nama kamu"
                                    aria-invalid={Boolean(errors.name)}
                                    aria-describedby={
                                        errors.name ? "register-name-error" : undefined
                                    }
                                />
                                <InputError
                                    id="register-name-error"
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Alamat email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                    aria-invalid={Boolean(errors.email)}
                                    aria-describedby={
                                        errors.email ? "register-email-error" : undefined
                                    }
                                />
                                <InputError id="register-email-error" message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Minimal 8 karakter"
                                    passwordrules={passwordRules}
                                    aria-invalid={Boolean(errors.password)}
                                    aria-describedby={
                                        errors.password ? "register-password-error" : undefined
                                    }
                                />
                                <InputError
                                    id="register-password-error"
                                    message={errors.password}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">Ulangi kata sandi</Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Ulangi kata sandi"
                                    passwordrules={passwordRules}
                                    aria-invalid={Boolean(errors.password_confirmation)}
                                    aria-describedby={
                                        errors.password_confirmation
                                            ? "register-password-confirmation-error"
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="register-password-confirmation-error"
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Buat akun gratis
                            </Button>
                        </div>

                        <div className="text-muted-foreground text-center text-sm">
                            Sudah punya akun? <TextLink href={login()}>Masuk</TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: "Mulai melayani lebih cepat",
    description: "Buat akun owner dan coba Meja gratis selama 14 hari",
};
