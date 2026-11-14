package com.vidhi.voters.model;

public enum Gender {
    MALE("Male"), FEMALE("Female"), OTHER("Other");

    private final String label;
    Gender(String label) { this.label = label; }
    public String getLabel() { return label; }

    /** Lenient parser used by the Excel importer (accepts M/F/O, Male, female, etc.). */
    public static Gender parse(String s) {
        if (s == null || s.isBlank()) return null;
        String v = s.trim().toUpperCase();
        if (v.startsWith("M")) return MALE;
        if (v.startsWith("F")) return FEMALE;
        if (v.startsWith("O") || v.startsWith("T")) return OTHER;
        return null;
    }
}
