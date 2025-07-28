<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="text" encoding="iso-8859-1"/>
    <!-- https://stackoverflow.com/a/5738296/304151 -->
    <xsl:template match="*/text()[string-length(normalize-space()) > 0]">
        <xsl:value-of select="normalize-space()"/>
    </xsl:template>
    <xsl:template match="*/text()[not(string-length(normalize-space()) > 0)]" />
    <xsl:template match="problem">
        <xsl:value-of select="file"/>
        <!-- https://stackoverflow.com/a/25690036/304151 -->
        <xsl:if test="position () &lt; last()">
            <xsl:text>&#xA;</xsl:text>
        </xsl:if>
    </xsl:template>
</xsl:stylesheet>