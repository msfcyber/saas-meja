import { Head, useForm } from "@inertiajs/react";
import { Mail, Pencil, Plus, ShieldCheck, UserMinus, UsersRound } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

type StaffMember = {
    id: number;
    name: string;
    email: string;
    status: "active" | "inactive";
    is_owner: boolean;
    role: string | null;
    role_label: string;
};

type RoleOption = {
    value: string;
    label: string;
};

type StaffForm = {
    email: string;
    role: string;
    status: "active" | "inactive";
};

type Props = {
    staff: StaffMember[];
    roles: RoleOption[];
    usage: { current: number; limit: number | null };
    can_add: boolean;
    limit_message: string | null;
};

const emptyForm: StaffForm = {
    email: "",
    role: "cashier",
    status: "active",
};

export default function Staff({ staff, roles, usage, can_add, limit_message }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [editingStaff, setEditingStaff] = useState<StaffMember | null>(null);
    const [deletingStaffId, setDeletingStaffId] = useState<number | null>(null);
    const form = useForm<StaffForm>(emptyForm);
    const subscriptionError = (form.errors as Record<string, string | undefined>).subscription;

    function openCreate() {
        setEditingStaff(null);
        form.reset();
        form.clearErrors();
        setIsOpen(true);
    }

    function openEdit(member: StaffMember) {
        setEditingStaff(member);
        form.setData("email", member.email);
        form.setData("role", member.role ?? "cashier");
        form.setData("status", member.status);
        form.clearErrors();
        setIsOpen(true);
    }

    function closeDialog() {
        setIsOpen(false);
        setEditingStaff(null);
        form.reset();
        form.clearErrors();
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (editingStaff) {
            form.patch(`/staff/${editingStaff.id}`, {
                preserveScroll: true,
                onSuccess: closeDialog,
            });

            return;
        }

        form.post("/staff", {
            preserveScroll: true,
            onSuccess: closeDialog,
        });
    }

    function remove(member: StaffMember) {
        if (!window.confirm(`Hapus ${member.name} dari workspace ini?`)) {
            return;
        }

        setDeletingStaffId(member.id);
        form.delete(`/staff/${member.id}`, {
            preserveScroll: true,
            onFinish: () => setDeletingStaffId(null),
            onError: (errors) => {
                const message = Object.values(errors)[0];

                toast.error("Staf gagal dihapus", {
                    description: message ?? "Coba lagi dalam beberapa saat.",
                });
            },
        });
    }

    return (
        <>
            <Head title="Staf & akses" />
            <div className="mx-auto w-full max-w-[1500px] flex-1 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">
                            Workspace bisnis
                        </p>
                        <h1 className="font-display mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Staf & akses
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                            Tambahkan akun terdaftar dan atur peran operasionalnya pada workspace
                            ini.
                        </p>
                    </div>
                    <Button
                        size="lg"
                        className="min-h-12 rounded-full"
                        onClick={openCreate}
                        disabled={!can_add}
                        title={can_add ? undefined : (limit_message ?? undefined)}
                    >
                        <Plus aria-hidden="true" /> Tambah staf
                    </Button>
                </div>

                {!can_add && limit_message && (
                    <div
                        className="mt-6 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-200"
                        role="alert"
                    >
                        {limit_message}
                    </div>
                )}

                <section className="mt-8 grid gap-4 sm:grid-cols-3">
                    <article className="flex items-center gap-4 rounded-[1.3rem] border bg-card p-5">
                        <span className="flex size-11 items-center justify-center rounded-xl bg-secondary text-primary">
                            <UsersRound className="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <p className="text-xs text-muted-foreground">Staf aktif</p>
                            <p className="mt-1 text-xl font-bold">
                                {usage.current}
                                <span className="ml-1 text-xs font-normal text-muted-foreground">
                                    / {usage.limit === null ? "tak terbatas" : usage.limit}
                                </span>
                            </p>
                        </div>
                    </article>
                    <article className="flex items-center gap-4 rounded-[1.3rem] border bg-card p-5">
                        <span className="flex size-11 items-center justify-center rounded-xl bg-secondary text-primary">
                            <ShieldCheck className="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <p className="text-xs text-muted-foreground">Peran tersedia</p>
                            <p className="mt-1 text-xl font-bold">{roles.length}</p>
                        </div>
                    </article>
                    <article className="flex items-center gap-4 rounded-[1.3rem] border bg-card p-5">
                        <span className="flex size-11 items-center justify-center rounded-xl bg-secondary text-primary">
                            <Mail className="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <p className="text-xs text-muted-foreground">Model akses</p>
                            <p className="mt-1 text-xl font-bold">Tenant-wide</p>
                        </div>
                    </article>
                </section>

                <section className="mt-5 overflow-hidden rounded-[1.5rem] border bg-card">
                    <div className="border-b p-5 sm:p-6">
                        <h2 className="font-display text-xl font-bold">Anggota workspace</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Owner tidak dapat dihapus atau diturunkan perannya dari halaman ini.
                        </p>
                    </div>
                    <div className="divide-y">
                        {staff.length === 0 ? (
                            <div className="p-8 text-center" role="status">
                                <UsersRound
                                    className="mx-auto size-8 text-primary"
                                    aria-hidden="true"
                                />
                                <h3 className="font-display mt-4 text-xl font-bold">
                                    Belum ada anggota tambahan
                                </h3>
                                <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                                    Tambahkan akun terdaftar untuk membantu mengelola order dan
                                    menu.
                                </p>
                                {can_add && (
                                    <Button className="mt-5 rounded-full" onClick={openCreate}>
                                        <Plus aria-hidden="true" /> Tambah staf
                                    </Button>
                                )}
                            </div>
                        ) : (
                            staff.map((member) => (
                                <article
                                    key={member.id}
                                    className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"
                                >
                                    <div className="flex min-w-0 items-start gap-3">
                                        <div className="flex size-11 shrink-0 items-center justify-center rounded-full bg-secondary font-bold text-primary">
                                            {member.name.slice(0, 1).toUpperCase()}
                                        </div>
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="truncate font-semibold">
                                                    {member.name}
                                                </h3>
                                                <span
                                                    className={`rounded-full px-2.5 py-1 text-[11px] font-bold ${member.status === "active" ? "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300" : "bg-muted text-muted-foreground"}`}
                                                >
                                                    {member.status === "active"
                                                        ? "Aktif"
                                                        : "Nonaktif"}
                                                </span>
                                            </div>
                                            <p className="mt-1 truncate text-sm text-muted-foreground">
                                                {member.email}
                                            </p>
                                            <p className="mt-2 text-xs font-semibold text-primary">
                                                {member.is_owner ? "Owner" : member.role_label}
                                            </p>
                                        </div>
                                    </div>
                                    {!member.is_owner && (
                                        <div className="flex shrink-0 gap-2 sm:justify-end">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="min-h-10 rounded-full"
                                                onClick={() => openEdit(member)}
                                                disabled={
                                                    form.processing || deletingStaffId !== null
                                                }
                                            >
                                                <Pencil aria-hidden="true" /> Edit
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="min-h-10 rounded-full text-destructive hover:text-destructive"
                                                onClick={() => remove(member)}
                                                disabled={
                                                    form.processing || deletingStaffId !== null
                                                }
                                                aria-busy={deletingStaffId === member.id}
                                            >
                                                <UserMinus aria-hidden="true" /> Hapus
                                            </Button>
                                        </div>
                                    )}
                                </article>
                            ))
                        )}
                    </div>
                </section>
            </div>

            <Dialog open={isOpen} onOpenChange={(open) => !open && closeDialog()}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingStaff ? "Edit staf" : "Tambah staf"}</DialogTitle>
                        <DialogDescription>
                            Hanya akun yang sudah terdaftar yang dapat ditambahkan ke workspace.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="grid gap-4">
                        <InputError id="staff-subscription-error" message={subscriptionError} />
                        {!editingStaff && (
                            <div className="grid gap-2">
                                <Label htmlFor="staff-email">Email akun</Label>
                                <Input
                                    id="staff-email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) => form.setData("email", event.target.value)}
                                    autoComplete="email"
                                    autoFocus
                                    required
                                    placeholder="staf@example.com"
                                    aria-invalid={Boolean(form.errors.email)}
                                    aria-describedby={
                                        form.errors.email ? "staff-email-error" : undefined
                                    }
                                />
                                <InputError id="staff-email-error" message={form.errors.email} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="staff-role">Peran</Label>
                            <select
                                id="staff-role"
                                value={form.data.role}
                                onChange={(event) => form.setData("role", event.target.value)}
                                className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                aria-invalid={Boolean(form.errors.role)}
                                aria-describedby={form.errors.role ? "staff-role-error" : undefined}
                            >
                                {roles.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </select>
                            <InputError id="staff-role-error" message={form.errors.role} />
                        </div>

                        {editingStaff && (
                            <div className="grid gap-2">
                                <Label htmlFor="staff-status">Status keanggotaan</Label>
                                <select
                                    id="staff-status"
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setData(
                                            "status",
                                            event.target.value as StaffForm["status"],
                                        )
                                    }
                                    className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                    aria-invalid={Boolean(form.errors.status)}
                                    aria-describedby={
                                        form.errors.status ? "staff-status-error" : undefined
                                    }
                                >
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </select>
                                <InputError id="staff-status-error" message={form.errors.status} />
                            </div>
                        )}

                        <DialogFooter>
                            <Button type="button" variant="secondary" onClick={closeDialog}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? "Menyimpan..." : "Simpan"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Staff.layout = { breadcrumbs: [{ title: "Staf & akses", href: "/staff" }] };
