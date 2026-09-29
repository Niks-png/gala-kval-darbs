{{-- Adds products from .add-to-cart-form forms without reloading and shows a confirmation. --}}
<div id="cart-success-toast" class="pointer-events-none fixed bottom-6 end-6 z-50 hidden max-w-sm rounded-lg bg-emerald-600 px-4 py-3 text-sm font-medium text-white shadow-lg" role="status">
    {{ __('Produkts veiksmīgi pievienots iepirkuma sarakstam') }}
</div>

<script>
    document.querySelectorAll('.add-to-cart-form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                },
            });

            if (!response.ok) {
                return;
            }

            const toast = document.getElementById('cart-success-toast');
            toast.classList.remove('hidden');

            window.setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        });
    });
</script>
