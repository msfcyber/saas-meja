export type MenuItem = {
    id: number;
    name: string;
    category: string;
    description: string;
    price: number;
    image: string;
    popular?: boolean;
    spicy?: boolean;
};

export const menuItems: MenuItem[] = [
    {
        id: 1,
        name: "Nasi Ayam Kecombrang",
        category: "Makanan utama",
        description: "Ayam panggang, sambal kecombrang, urap sayur, dan nasi daun jeruk.",
        price: 48000,
        image: "https://images.unsplash.com/photo-1512058564366-18510be2db19?auto=format&fit=crop&w=900&q=85",
        popular: true,
        spicy: true,
    },
    {
        id: 2,
        name: "Sate Maranggi",
        category: "Makanan utama",
        description: "Daging sapi berbumbu ketumbar, acar tomat, dan sambal kecap.",
        price: 56000,
        image: "https://images.unsplash.com/photo-1529563021893-cc83c992d75d?auto=format&fit=crop&w=900&q=85",
        popular: true,
    },
    {
        id: 3,
        name: "Mie Tek-Tek Kampung",
        category: "Makanan utama",
        description: "Mie telur, suwiran ayam, kol, sawi, dan telur orak-arik.",
        price: 42000,
        image: "https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=900&q=85",
        spicy: true,
    },
    {
        id: 4,
        name: "Tahu Lada Garam",
        category: "Camilan",
        description: "Tahu sutra renyah dengan cabai, daun bawang, dan bawang putih.",
        price: 32000,
        image: "https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=900&q=85",
    },
    {
        id: 5,
        name: "Es Kopi Aren",
        category: "Minuman",
        description: "Espresso house blend, susu segar, dan gula aren organik.",
        price: 28000,
        image: "https://images.unsplash.com/photo-1461023058943-07fcbe16d735?auto=format&fit=crop&w=900&q=85",
        popular: true,
    },
    {
        id: 6,
        name: "Klepon Cheesecake",
        category: "Pencuci mulut",
        description: "Cheesecake pandan, kelapa parut, dan lelehan gula jawa.",
        price: 38000,
        image: "https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=900&q=85",
    },
];

export const formatCurrency = (value: number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(value);

export const orders = [
    {
        number: "#A-1048",
        table: "Meja 08",
        customer: "Raka",
        total: 124000,
        items: 3,
        age: "02:14",
        status: "paid",
    },
    {
        number: "#A-1047",
        table: "Meja 03",
        customer: "Nadia",
        total: 86000,
        items: 2,
        age: "07:32",
        status: "accepted",
    },
    {
        number: "#A-1046",
        table: "Meja 11",
        customer: "Guest",
        total: 192000,
        items: 5,
        age: "12:05",
        status: "preparing",
    },
    {
        number: "#A-1045",
        table: "Meja 05",
        customer: "Dita",
        total: 74000,
        items: 2,
        age: "18:42",
        status: "ready",
    },
];
