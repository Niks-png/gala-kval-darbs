<x-layouts::app :title="__('Receptes')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">Ko pagatavot?</flux:heading>
            <flux:text class="mt-2">Ievadi produktus, kas tev ir mājās, un atrodi piemērotas receptes.</flux:text>
        </div>

        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <form id="ingredient-form" class="flex flex-col gap-3 sm:flex-row">
                <input
                    id="ingredient-input"
                    type="text"
                    autocomplete="off"
                    placeholder="Piemēram, kartupeļi, piens vai vista"
                    class="min-h-10 flex-1 rounded-lg border border-neutral-300 bg-transparent px-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-600"
                >
                <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                    Pievienot
                </button>
            </form>
            <div id="ingredient-list" class="mt-3 flex flex-wrap gap-2"></div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button id="find-recipes" type="button" class="rounded-lg bg-neutral-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-neutral-700 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
                    Meklēt receptes
                </button>
                <button id="clear-ingredients" type="button" class="text-sm text-neutral-500 underline-offset-4 hover:underline">
                    Notīrīt
                </button>
            </div>
            <p class="mt-3 text-xs text-neutral-500">Jo vairāk produktu pievienosi, jo precīzāki būs rezultāti.</p>
        </div>

        <div id="recipe-status" class="hidden rounded-lg border border-neutral-200 p-4 text-sm dark:border-neutral-700"></div>
        <div id="recipe-results" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"></div>
    </div>

    <dialog id="recipe-dialog" class="w-[min(92vw,720px)] rounded-2xl border border-neutral-200 bg-white p-0 text-neutral-900 shadow-2xl backdrop:bg-black/50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white">
        <div id="recipe-details" class="max-h-[85vh] overflow-y-auto"></div>
    </dialog>

    <script>
        (() => {
            const api = 'https://www.themealdb.com/api/json/v1/1';
            const form = document.getElementById('ingredient-form');
            const input = document.getElementById('ingredient-input');
            const list = document.getElementById('ingredient-list');
            const results = document.getElementById('recipe-results');
            const status = document.getElementById('recipe-status');
            const dialog = document.getElementById('recipe-dialog');
            const details = document.getElementById('recipe-details');
            let ingredients = [];

            const aliases = {
                'kartupeļi': 'potato', 'kartupelis': 'potato', 'piens': 'milk',
                'vista': 'chicken', 'vistas gaļa': 'chicken', 'olas': 'egg',
                'ola': 'egg', 'tomāti': 'tomato', 'tomāts': 'tomato',
                'sīpoli': 'onion', 'sīpols': 'onion', 'ķiploki': 'garlic',
                'burkāni': 'carrot', 'burkāns': 'carrot', 'rīsi': 'rice',
                'makaroni': 'pasta', 'siers': 'cheese', 'cūkgaļa': 'pork',
                'liellopa gaļa': 'beef', 'zivs': 'fish', 'maize': 'bread',
                'sviests': 'butter', 'milti': 'flour', 'āboli': 'apple',
                'krējums': 'cream', 'pupiņas': 'beans'
            };

            const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            }[character]));

            function setStatus(message, visible = true) {
                status.textContent = message;
                status.classList.toggle('hidden', !visible);
            }

            function renderIngredients() {
                list.innerHTML = ingredients.map((ingredient, index) => `
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-sm text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                        ${escapeHtml(ingredient)}
                        <button type="button" data-remove="${index}" class="font-bold" aria-label="Noņemt ${escapeHtml(ingredient)}">×</button>
                    </span>
                `).join('');
            }

            function addIngredient(value) {
                const ingredient = value.trim().toLowerCase();
                if (ingredient && !ingredients.includes(ingredient)) {
                    ingredients.push(ingredient);
                    renderIngredients();
                }
                input.value = '';
                input.focus();
            }

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                addIngredient(input.value);
            });

            list.addEventListener('click', (event) => {
                const index = event.target.dataset.remove;
                if (index !== undefined) {
                    ingredients.splice(Number(index), 1);
                    renderIngredients();
                }
            });

            document.getElementById('clear-ingredients').addEventListener('click', () => {
                ingredients = [];
                renderIngredients();
                results.innerHTML = '';
                setStatus('', false);
            });

            document.getElementById('find-recipes').addEventListener('click', async () => {
                if (!ingredients.length) {
                    setStatus('Vispirms pievieno vismaz vienu produktu.');
                    return;
                }

                results.innerHTML = '';
                setStatus('Meklējam receptes...', true);
                try {
                    const responses = await Promise.all(ingredients.map((ingredient) => {
                        const apiIngredient = aliases[ingredient] || ingredient;
                        return fetch(`${api}/filter.php?i=${encodeURIComponent(apiIngredient)}`).then((response) => response.json());
                    }));
                    const recipes = new Map();
                    responses.forEach((response) => (response.meals || []).forEach((meal) => {
                        recipes.set(meal.idMeal, { ...meal, matches: (recipes.get(meal.idMeal)?.matches || 0) + 1 });
                    }));
                    const sorted = [...recipes.values()].sort((a, b) => b.matches - a.matches).slice(0, 18);
                    setStatus(sorted.length ? `Atrastas ${sorted.length} receptes.` : 'Šiem produktiem receptes netika atrastas.');
                    results.innerHTML = sorted.map((meal) => `
                        <article class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                            <img src="${escapeHtml(meal.strMealThumb)}" alt="" class="h-44 w-full object-cover">
                            <div class="p-4">
                                <h2 class="font-semibold">${escapeHtml(meal.strMeal)}</h2>
                                <p class="mt-1 text-xs text-neutral-500">Atbilst ${meal.matches} no ${ingredients.length} produktiem</p>
                                <button type="button" data-recipe="${meal.idMeal}" class="mt-4 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Skatīt recepti</button>
                            </div>
                        </article>
                    `).join('');
                } catch (error) {
                    setStatus('Receptes neizdevās ielādēt. Pārbaudi interneta savienojumu un mēģini vēlreiz.');
                }
            });

            results.addEventListener('click', async (event) => {
                const recipeId = event.target.dataset.recipe;
                if (!recipeId) return;
                details.innerHTML = '<div class="p-6">Ielādējam recepti...</div>';
                dialog.showModal();
                try {
                    const response = await fetch(`${api}/lookup.php?i=${recipeId}`);
                    const meal = (await response.json()).meals?.[0];
                    if (!meal) throw new Error('Recipe not found');
                    const pairs = Array.from({ length: 20 }, (_, index) => [meal[`strIngredient${index + 1}`], meal[`strMeasure${index + 1}`]])
                        .filter(([name]) => name);
                    details.innerHTML = `
                        <img src="${escapeHtml(meal.strMealThumb)}" alt="" class="h-56 w-full object-cover">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4">
                                <h2 class="text-2xl font-semibold">${escapeHtml(meal.strMeal)}</h2>
                                <button type="button" data-close class="text-2xl text-neutral-500" aria-label="Aizvērt">×</button>
                            </div>
                            <h3 class="mt-6 font-semibold">Sastāvdaļas</h3>
                            <ul class="mt-2 grid gap-1 sm:grid-cols-2">${pairs.map(([name, measure]) => `<li class="text-sm">${escapeHtml(measure || '')} ${escapeHtml(name)}</li>`).join('')}</ul>
                            <h3 class="mt-6 font-semibold">Pagatavošana</h3>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6">${escapeHtml(meal.strInstructions || 'Norādes nav pieejamas.')}</p>
                        </div>
                    `;
                } catch (error) {
                    details.innerHTML = '<div class="p-6">Recepti neizdevās ielādēt.</div>';
                }
            });

            details.addEventListener('click', (event) => {
                if (event.target.dataset.close !== undefined) dialog.close();
            });
        })();
    </script>
</x-layouts::app>
