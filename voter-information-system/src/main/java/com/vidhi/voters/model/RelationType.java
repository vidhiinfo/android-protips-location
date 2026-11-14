package com.vidhi.voters.model;

public enum RelationType {
    FATHER("Father"), HUSBAND("Husband"), MOTHER("Mother"), OTHER("Other");

    private final String label;
    RelationType(String label) { this.label = label; }
    public String getLabel() { return label; }

    public static RelationType parse(String s) {
        if (s == null || s.isBlank()) return FATHER;
        String v = s.trim().toUpperCase();
        if (v.startsWith("H")) return HUSBAND;
        if (v.startsWith("M")) return MOTHER;
        if (v.startsWith("O")) return OTHER;
        return FATHER;
    }
}
