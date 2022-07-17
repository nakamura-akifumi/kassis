package kassiscore

import (
	"testing"
)

func TestSetupSolrSuccess(t *testing.T) {
	err := SetupSolr("kassiscore_test")
	if err != nil {
		t.Fatal("failed test")
	}
}
