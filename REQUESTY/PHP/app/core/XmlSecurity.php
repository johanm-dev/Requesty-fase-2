<?php
/**
 * Validador XML Anti-XXE estricto para documentos DIAN (factura.xml).
 */
class XmlSecurity {
    public static function validateSafeXml(?string $xml): ?string {
        if ($xml === null) {
            return null;
        }
        $trimmed = trim($xml);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/<!DOCTYPE|<!ENTITY|\bSYSTEM\b|\bPUBLIC\b/i', $trimmed) === 1) {
            throw new DomainValidationException('XML rechazado por politica anti-XXE: DOCTYPE y entidades externas prohibidas.');
        }

        $prevUseErrors = libxml_use_internal_errors(true);
        if (function_exists('libxml_set_external_entity_loader')) {
            libxml_set_external_entity_loader(static fn() => null);
        }

        try {
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->resolveExternals = false;
            $dom->substituteEntities = false;
            $ok = $dom->loadXML($trimmed, LIBXML_NONET | LIBXML_NOCDATA);
            if (!$ok || $dom->doctype !== null) {
                throw new DomainValidationException('El XML proporcionado no es valido o contiene declaraciones prohibidas.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prevUseErrors);
        }

        return $trimmed;
    }
}
