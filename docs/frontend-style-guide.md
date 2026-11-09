# Guía de estilo para interfaces del ERP

## Objetivo

Las interfaces deben sentirse como software empresarial mantenido de forma consistente, no como una colección de componentes promocionales o plantillas independientes. La prioridad es facilitar tareas operativas con jerarquía clara, densidad razonable y una apariencia sobria.

## Principios obligatorios

1. **La función define la apariencia.** Cada color, borde, icono o bloque destacado debe comunicar jerarquía, estado o acción.
2. **La interfaz debe integrarse con el producto existente.** Antes de diseñar un componente nuevo, revisar pantallas equivalentes y reutilizar sus patrones.
3. **La información domina sobre la decoración.** Evitar encabezados monumentales, tarjetas innecesarias, gradientes, adornos y espacios vacíos sin función.
4. **El color es semántico.** Usar blanco y grises como base. Reservar verde para éxito, rojo para error, ámbar para advertencia y el color de marca para selección o acción principal.
5. **Una acción principal por sección.** Las acciones secundarias deben tener menor peso visual.

## Lenguaje visual

- Base: blanco, `slate` y grises neutros.
- Bordes: finos y discretos; evitar contenedores anidados con fondos de color.
- Radios: preferir `rounded-md` o `rounded-lg`; `rounded-xl` puede usarse en contenedores principales para suavizar la composición. No usar cápsulas ni `rounded-2xl` de forma generalizada.
- Sombras: utilizar `shadow-sm` solamente cuando ayude a separar planos.
- Tipografía: títulos con `font-semibold`; reservar `font-bold` para cifras o datos críticos. Evitar el uso repetitivo de `font-black`.
- Etiquetas: utilizar tamaño legible y capitalización normal. Evitar microtexto en mayúsculas con espaciado excesivo.
- Iconos: solo cuando aporten reconocimiento o reduzcan texto; no utilizarlos como decoración.

La sobriedad no implica construir una interfaz totalmente cuadrada o plana. Se permiten radios moderados, fondos neutros suaves y sombras discretas cuando ayuden a crear una composición amable y coherente. La pantalla debe equilibrar densidad operativa con aire visual, procurando que el resumen y las acciones frecuentes sean visibles en el primer viewport de escritorio.

## Componentes

### Formularios

- Etiquetas visibles y descriptivas.
- Controles con altura y radio consistentes.
- Texto de ayuda solo cuando explique una regla o consecuencia real.
- Errores cerca del campo y en rojo oscuro, sin bloques llamativos innecesarios.

### Tarjetas y paneles

- Usar paneles únicamente para agrupar información relacionada.
- Preferir encabezado, divisor y contenido sobre fondos de colores.
- No convertir cada dato en una tarjeta independiente si una fila o tabla es más clara.

### Estados

- Éxito, advertencia y error pueden usar color, siempre con contraste accesible.
- Los estados normales o inactivos deben permanecer neutrales.
- No depender exclusivamente del color: acompañarlo con texto explícito.

### Tablas

- Encabezados discretos, alineación consistente y números tabulares.
- Evitar badges en cada celda cuando el texto simple sea suficiente.
- Las acciones por fila deben ser compactas y previsibles.
- Cuando existan muchas acciones, destacar una acción principal con texto y agrupar visualmente las secundarias; evitar filas de iconos sueltos con colores distintos.

## Contenido

- Español profesional y directo.
- Sin emojis.
- Evitar frases promocionales, grandilocuentes o genéricas.
- Nombrar las acciones por su resultado: `Registrar tasa`, `Generar orden`, `Guardar cambios`.

## Revisión antes de entregar

- ¿La pantalla parece parte del mismo ERP?
- ¿Hay color sin significado funcional?
- ¿Se abusó de tarjetas, cápsulas, sombras o bordes redondeados?
- ¿La jerarquía se entiende sin depender del color?
- ¿La acción principal es evidente?
- ¿Los datos financieros utilizan alineación y formato consistentes?
- ¿La vista funciona en escritorio y móvil sin perder información?

## Referencia interna

Para pantallas administrativas, usar como referencia base el módulo `Equipo interno` (`resources/views/livewire/administracion/usuarios/`). Reutilizar su escala de títulos, tipografía, densidad de filas, botones principales y estructura de tablas para mantener continuidad visual entre módulos.
