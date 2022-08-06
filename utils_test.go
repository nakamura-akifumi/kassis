package kassiscore

import (
	"testing"
)

func TestNormalizeISBN(t *testing.T) {
	_, err := NormalizeISBN("")
	if err == nil {
		t.Fatal("failed test")
	}

	_, err = NormalizeISBN("123")
	if err == nil {
		t.Fatal("failed test")
	}

	s, err := NormalizeISBN("4062145901")
	if err != nil {
		t.Fatal("failed test")
	}
	if s != "4062145901" {
		t.Fatal("failed test")
	}

	s, err = NormalizeISBN("4062145901 ")
	if err != nil {
		t.Fatal("failed test")
	}
	if s != "4062145901" {
		t.Fatal("failed test")
	}

	s, err = NormalizeISBN("978-4-915512-69-8")
	if err != nil {
		t.Fatal("failed test")
	}
	if s != "9784915512698" {
		t.Fatal("failed test")
	}

	s, err = NormalizeISBN("978-4-915512a69*8")
	if err == nil {
		t.Fatal("failed test")
	}

}

func TestArrayContains(t *testing.T) {
	ar := []string{"c", "b", "a"}
	if ArrayContains(ar, "a") == false {
		t.Fatal("failed test")
	}
	if ArrayContains(ar, "z") == true {
		t.Fatal("failed test")
	}

}
