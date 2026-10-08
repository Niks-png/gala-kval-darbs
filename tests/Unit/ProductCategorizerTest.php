<?php

use App\Services\ProductCategorizer;

test('products are categorized by what they are, not by flavour', function (string $title, string $category) {
    expect((new ProductCategorizer)->categorize($title))->toBe($category);
})->with([
    // Flavours and fillings must not decide the category.
    ["ČIPSI LAY'S AR SIERA GARŠU 180G", 'Uzkodas, rieksti un sēklas'],
    ['KUKURŪZAS ČIPSI DORITOS NACHO AR SIERA GARŠU 90G', 'Uzkodas, rieksti un sēklas'],
    ['KARTUPEĻU PLĀKSNES LONGCHIPS SK.KRĒJUMA UN SĪPOLU 75G', 'Uzkodas, rieksti un sēklas'],
    ['Kart. plāksnes LONGCHIPS medus BBQ 75g (9.20 €/kg); (10.53 €/kg)', 'Uzkodas, rieksti un sēklas'],
    ['NŪDELES REEVA AR VISTAS GAĻAS BULJONU MĀJAS GAUMĒ 60G', 'Graudaugi, makaroni un milti'],
    ['PIENA ŠOKOLĀDE MILKA DAIM 90G', 'Saldumi un deserti'],
    ['VAFELES TIP TOP AR PIENA GARŠU 150G', 'Saldumi un deserti'],
    ['3D mango saldējums uz kociņa BOUJEE 75g', 'Saldumi un deserti'],
    ['SIERA DESA TIP TOP KAUSĒTA KŪPINĀTA 40% 330G', 'Gaļa un gaļas izstrādājumi'],
    ['DESIŅAS RĪGAS MIESNIEKS MĀRTIŅA AR SIERU 300G', 'Gaļa un gaļas izstrādājumi'],
    ['TOMĀTU MĒRCE TIP TOP 455G', 'Mērces, eļļas un garšvielas'],
    ['Franču bagete ar ķiploku sviestu, 152 g (3,75 €/kg)', 'Maize un maizes izstrādājumi'],
    ['BARĪBA KAĶIEM SAUSĀ FRISKIES VISTA-DĀRZEŅI 800G', 'Dzīvnieku barība'],
    ['SMILTIS KAĶIEM TIP TOP CEMENTĒJOŠAS 5L', 'Dzīvnieku barība'],
    ['BIEZENIS HIPP BURKĀNI AR RĪSIEM UN TEĻA GAĻU 4+ 190G', 'Bērnu preces'],
    ['Trauku mazg. līdz. maš. FINISH sāls 1,5kg', 'Mājsaimniecības preces'],
    ['Ūdens mīkstinātājs CALGON 750ml (9.32 €/l); (15.32 €/l)', 'Mājsaimniecības preces'],
    ['ZOBU PASTA COLGATE MAX FRESH 75ML', 'Higiēna un kosmētika'],
    ['Dušas želeja PALMOLIVE Fig&Milk 500ml', 'Higiēna un kosmētika'],

    // Non-food goods, and words that mean something else outside food.
    ['Siev. zeķb. Favorite 50 d 42141 nero 4', 'Apģērbi'],
    ['Bērnu zeķes Mywear garās 3p 31/33', 'Apģērbi'],
    ['Pusdienu šķīvis Luminarc 25cm AW26', 'Mājsaimniecības preces'],
    ['Maizes nazis Berlinger Haus 20cm AW26', 'Mājsaimniecības preces'],
    ['Gaismas virtene, 120 LED, krāsaina CH26', 'Mājsaimniecības preces'],
    ['Svece Cozy Home ķirbis D10xh6.5cm HW26', 'Mājsaimniecības preces'],
    ['Konstr. Lego Cūkas Mazuļu Māja 21268', 'Rotaļlietas un hobiji'],
    ['Bezvadu USB datorpele Havit MS78GT', 'Elektronika'],
    ['UZTURA BAGĀTINĀTĀJS MÖLLERS ZIVJU EĻĻA ORĢINĀLĀ GARŠA 250ML', 'Uztura bagātinātāji'],
    ['Cūkgaļas lāpstiņa Forevers marin., kūpin. kg', 'Gaļa un gaļas izstrādājumi'],

    // Shop abbreviations.
    ['Jog. SKYR ISLANDES bez pied. 400g', 'Piena produkti un olas'],
    ['Biezp. Annele amfora prot.ar mango 0,8 % 200g', 'Piena produkti un olas'],
    ['Kaf. pupiņas Andrito Brasil Bella Giana 250g', 'Dzērieni'],
    ['Gāz. dz. SPRITE Chill Zero 0,33L', 'Dzērieni'],
    ['MUSLI BATONIŅŠ NESTLE ZEMEŅU 35G', 'Saldumi un deserti'],

    // Plain products.
    ['PIENS OPĀ 2.5% 0.9L', 'Piena produkti un olas'],
    ['KEFĪRS TIP TOP 2.5% 1KG', 'Piena produkti un olas'],
    ['BIEZPIENA SIERIŅŠ ALMA VANIĻAS 40G', 'Piena produkti un olas'],
    ['Kūtī dētas olas, 1 iepak./10 gab.', 'Piena produkti un olas'],
    ['Cūkgaļas maltā gaļa WELL DONE, 400 g (4,23 €/kg)', 'Gaļa un gaļas izstrādājumi'],
    ['CĀĻU FILEJA RĪGAS MIESNIEKS ATDZESĒTA ~500G', 'Gaļa un gaļas izstrādājumi'],
    ['SIĻĶU FILEJA LOMS AR SĪPOLIEM UN BALTO MĒRCI 200G', 'Zivis un jūras veltes'],
    ['RUDZU MAIZE TIP TOP 800G', 'Maize un maizes izstrādājumi'],
    ['TOSTERMAIZE TIP TOP 500G', 'Maize un maizes izstrādājumi'],
    ['AUZU PĀRSLAS TIP TOP 400G', 'Graudaugi, makaroni un milti'],
    ['Jaunie kartupeļi, 1 kg', 'Augļi un dārzeņi'],
    ['TOMĀTI FIORINO SAVĀ SULĀ MIZOTI 400G', 'Augļi un dārzeņi'],
    ['PUPIŅAS FIORINO SVIESTA 400G', 'Augļi un dārzeņi'],
    ['D DZĒRIENS COCA COLA GĀZ. 850ML PET', 'Dzērieni'],
    ['KAFIJAS KAPSULAS LAVAZZA A MODO MIO ORO 120G', 'Dzērieni'],
    ['KONSERVI TUNCIS KAIJA SAVĀ SULĀ 160G', 'Gatavie ēdieni un konservi'],
    ['Āzijas virtuves produktiem KIKKOMANN', ProductCategorizer::OTHER],
]);
