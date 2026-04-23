# Módulo: Woo Tweaks

El módulo `woo` contiene extensiones y ajustes relacionados con WooCommerce que Politeia Learning usa para ventas, métricas y UX de perfiles.

## Qué hace

- Agrega metaboxes/atributos a productos para asociarlos a un “owner” o programa.
- Expone métricas y tablas para creadores/administración (ventas, estudiantes, rankings).
- Añade ajustes financieros y de perfil vinculados a la experiencia de compras.
- Implementa templates/emails personalizados asociados a flujos WooCommerce cuando aplica.

## Puntos de entrada

- `init.php`: carga las clases del módulo e inicializa cada componente con `::init()`.

## Clases principales

- `PL_Woo_Product_Owner_Metabox`
- `PL_Woo_User_Sales_Metrics`, `PL_Woo_User_Sales_Table`
- `PL_Woo_User_Student_Metrics`, `PL_Woo_User_Student_Rankings`, `PL_Woo_User_Student_Detail`
- `PL_Woo_User_Profile_Settings`, `PL_Woo_Financial_Settings`
- `PL_Woo_Product_Program_Selector`
- `PL_Woo_Order_Split_Snapshot`
- `PL_Woo_Templates`, `PL_Woo_Emails`

## Dependencias

- WooCommerce activo (hooks, emails y data de orders/products).
